(function () {
    function normalizeBranchIds(branchIds) {
        return [...new Set((Array.isArray(branchIds) ? branchIds : [branchIds])
            .flatMap(value => String(value ?? '').split(','))
            .map(value => parseInt(value.trim(), 10))
            .filter(value => Number.isInteger(value) && value > 0))];
    }

    function start(options = {}) {
        const config = options.config || {};
        const branchIds = normalizeBranchIds(options.branchIds || config.branchIds || []);
        const events = Array.isArray(options.events) && options.events.length
            ? options.events
            : ['pos.transaction.completed', 'wallet.updated'];
        const status = typeof options.statusElement === 'string'
            ? document.getElementById(options.statusElement)
            : options.statusElement;
        const onUpdate = typeof options.onUpdate === 'function' ? options.onUpdate : () => Promise.resolve();
        const interval = Math.max(5000, parseInt(options.interval, 10) || 15000);
        let pusher = null;
        let pollingTimer = null;
        let visibilityHandler = null;
        let refreshInFlight = null;
        let refreshQueued = false;
        let connectedChannels = 0;
        let fallbackStarted = false;
        let stopped = false;

        const setStatus = (state, message) => {
            if (!status) return;
            status.classList.toggle('text-danger', state === 'error');
            status.classList.toggle('text-warning', state === 'warning');
            status.classList.toggle('text-success', state === 'success');
            status.classList.toggle('text-muted', !['error', 'warning', 'success'].includes(state));
            status.textContent = message;
        };

        const stopPolling = () => {
            if (pollingTimer) {
                clearInterval(pollingTimer);
                pollingTimer = null;
            }
        };

        const refresh = async (meta = {}) => {
            if (stopped || document.visibilityState !== 'visible') return;
            if (refreshInFlight) {
                refreshQueued = true;
                return refreshInFlight;
            }

            refreshInFlight = Promise.resolve(onUpdate(meta))
                .catch(error => {
                    console.error('[Branch realtime] Refresh failed:', error);
                    setStatus('error', 'Live update failed; showing last known data');
                })
                .finally(() => {
                    refreshInFlight = null;
                    if (refreshQueued && !stopped) {
                        refreshQueued = false;
                        refresh({ reason: 'queued' });
                    }
                });

            return refreshInFlight;
        };

        const startPolling = () => {
            if (stopped || pollingTimer) return;
            fallbackStarted = true;
            setStatus('warning', 'Live updates unavailable; polling fallback active');
            pollingTimer = setInterval(() => refresh({ reason: 'poll' }), interval);
            if (!visibilityHandler) {
                visibilityHandler = () => {
                    if (document.visibilityState === 'visible') refresh({ reason: 'visible' });
                };
                document.addEventListener('visibilitychange', visibilityHandler);
            }
            refresh({ reason: 'fallback' });
        };

        const handleConnected = () => {
            fallbackStarted = false;
            stopPolling();
            setStatus('success', 'Live updates connected');
        };

        const handleFallback = () => {
            if (stopped) return;
            startPolling();
        };

        const stop = () => {
            stopped = true;
            stopPolling();
            if (visibilityHandler) {
                document.removeEventListener('visibilitychange', visibilityHandler);
                visibilityHandler = null;
            }
            if (pusher) pusher.disconnect();
        };

        if (!config.enabled || typeof Pusher === 'undefined' || !branchIds.length) {
            setStatus('warning', branchIds.length ? 'Live updates starting with polling' : 'Live updates paused — no branch scope');
            startPolling();
            return { refresh, stop };
        }

        setStatus('muted', 'Live updates connecting...');
        refresh({ reason: 'initial' });

        try {
            pusher = new Pusher(config.key, {
                cluster: config.cluster,
                forceTLS: true,
                authEndpoint: config.authEndpoint,
                auth: { withCredentials: true }
            });

            branchIds.forEach(branchId => {
                const channel = pusher.subscribe(`private-pos-branch-${branchId}`);
                channel.bind('pusher:subscription_succeeded', () => {
                    connectedChannels += 1;
                    if (connectedChannels >= branchIds.length) handleConnected();
                });
                events.forEach(eventName => {
                    channel.bind(eventName, payload => refresh({
                        reason: 'event',
                        event: eventName,
                        payload
                    }));
                });
                channel.bind('pusher:subscription_error', subscriptionStatus => {
                    console.warn('[Branch realtime] Subscription failed:', subscriptionStatus);
                    handleFallback();
                });
            });

            pusher.connection.bind('state_change', states => {
                if (states.current === 'connected' && connectedChannels >= branchIds.length) {
                    handleConnected();
                } else if (['disconnected', 'unavailable', 'failed'].includes(states.current)) {
                    connectedChannels = 0;
                    handleFallback();
                }
            });
        } catch (error) {
            console.error('[Branch realtime] Pusher initialization failed:', error);
            handleFallback();
        }

        return { refresh, stop, get usingFallback() { return fallbackStarted; } };
    }

    window.TMSBranchRealtime = { start };
})();

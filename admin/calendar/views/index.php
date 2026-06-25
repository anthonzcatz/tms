<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

  <?php require_once dirname(dirname(dirname(__DIR__))) . '/admin/includes/head.php'; ?>
  <link href="<?php echo BASE_URL; ?>/resources/vendors/flatpickr/flatpickr.min.css" rel="stylesheet">

  <style>
    /* -------------------------------------------------- */
    /*  Calendar overrides – match Falcon public template  */
    /* -------------------------------------------------- */
    .calendar-outline {
      min-height: 600px;
    }
    .fc .fc-toolbar-title { font-size: 1rem; font-weight: 600; }
    .fc .fc-button { font-size: .8125rem; }
    /* Event label colours */
    .fc-event-primary   { background-color: #2c7be5 !important; border-color: #2c7be5 !important; }
    .fc-event-danger    { background-color: #e63757 !important; border-color: #e63757 !important; }
    .fc-event-success   { background-color: #00d27a !important; border-color: #00d27a !important; }
    .fc-event-warning   { background-color: #f5803e !important; border-color: #f5803e !important; }
    /* Dot on label select */
    .label-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
    /* View dropdown: hide checkmark by default, show on active */
    [data-fc-view] .icon-check { visibility: hidden; }
    [data-fc-view].active .icon-check { visibility: visible; }
    /* Detail modal icon rows */
    .event-detail-row { display:flex; gap:.75rem; align-items:flex-start; margin-bottom:1rem; }
    .event-detail-row .detail-icon { color:#9da9bb; font-size:1.1rem; margin-top:2px; flex-shrink:0; width:20px; text-align:center; }
    .event-detail-row .detail-body .detail-label { font-weight:600; font-size:.8125rem; margin-bottom:.2rem; }
    .event-detail-row .detail-body .detail-value { font-size:.8125rem; color:#5e6e82; }
    /* Subtle all-day toggle */
    #eventAllDay:checked + label { color: #2c7be5; font-weight:600; }
  </style>

<body>
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>
        var isFluid = JSON.parse(localStorage.getItem('isFluid'));
        if (isFluid) {
          var container = document.querySelector('[data-layout]');
          container.classList.remove('container');
          container.classList.add('container-fluid');
        }
      </script>

      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar-double-top.php'; ?>
      <?php else: ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/sidebar.php'; ?>
      <?php endif; ?>

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      <div class="content">
        <?php if (NAVBAR_POSITION === 'combo'): ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar-top.php'; ?>
        <?php elseif (NAVBAR_POSITION === 'vertical'): ?>
          <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/navbar.php'; ?>
        <?php endif; ?>
      <?php endif; ?>

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/">Dashboard</a></li>
            <li class="breadcrumb-item active">Calendar</li>
          </ol>
        </nav>

        <!-- Calendar Card -->
        <div class="card overflow-hidden">
          <div class="card-header">
            <div class="row gx-0 align-items-center">
              <!-- Prev / Next -->
              <div class="col-auto d-flex justify-content-end order-md-1">
                <button class="btn icon-item icon-item-sm shadow-none p-0 me-1 ms-md-2" type="button" data-event="prev" data-bs-toggle="tooltip" title="Previous">
                  <span class="fas fa-arrow-left"></span>
                </button>
                <button class="btn icon-item icon-item-sm shadow-none p-0 me-1 me-lg-2" type="button" data-event="next" data-bs-toggle="tooltip" title="Next">
                  <span class="fas fa-arrow-right"></span>
                </button>
              </div>
              <!-- Title -->
              <div class="col-auto col-md-auto order-md-2">
                <h4 class="mb-0 fs-9 fs-sm-8 fs-lg-7 calendar-title"></h4>
              </div>
              <!-- Today -->
              <div class="col col-md-auto d-flex justify-content-end order-md-3">
                <button class="btn btn-falcon-primary btn-sm" type="button" data-event="today">Today</button>
              </div>
              <!-- HR divider on mobile -->
              <div class="col-md-auto d-md-none"><hr /></div>
              <!-- Add Schedule -->
              <div class="col-auto d-flex order-md-0">
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addEventModal">
                  <span class="fas fa-plus me-2"></span>Add Schedule
                </button>
              </div>
              <!-- View selector -->
              <div class="col d-flex justify-content-end order-md-2">
                <div class="dropdown font-sans-serif me-md-2">
                  <button class="btn btn-falcon-default text-600 btn-sm dropdown-toggle dropdown-caret-none"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span data-view-title>Month View</span>
                    <span class="fas fa-sort ms-2 fs-10"></span>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end border py-2">
                    <a class="active dropdown-item d-flex justify-content-between" href="#!" data-fc-view="dayGridMonth">
                      Month View<span class="icon-check"><span class="fas fa-check" data-fa-transform="down-4 shrink-4"></span></span>
                    </a>
                    <a class="dropdown-item d-flex justify-content-between" href="#!" data-fc-view="timeGridWeek">
                      Week View<span class="icon-check"><span class="fas fa-check" data-fa-transform="down-4 shrink-4"></span></span>
                    </a>
                    <a class="dropdown-item d-flex justify-content-between" href="#!" data-fc-view="timeGridDay">
                      Day View<span class="icon-check"><span class="fas fa-check" data-fa-transform="down-4 shrink-4"></span></span>
                    </a>
                    <a class="dropdown-item d-flex justify-content-between" href="#!" data-fc-view="listWeek">
                      List View<span class="icon-check"><span class="fas fa-check" data-fa-transform="down-4 shrink-4"></span></span>
                    </a>
                    <a class="dropdown-item d-flex justify-content-between" href="#!" data-fc-view="listYear">
                      Year View<span class="icon-check"><span class="fas fa-check" data-fa-transform="down-4 shrink-4"></span></span>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body p-0 scrollbar">
            <div class="calendar-outline" id="tmsCalendar"></div>
          </div>
        </div>

      <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
      </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- ===================== ADD / EDIT EVENT MODAL ===================== -->
  <div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content border">
        <form id="addEventForm" autocomplete="off" novalidate>
          <input type="hidden" id="eventId" value="">
          <div class="modal-header px-x1 bg-body-tertiary border-bottom-0">
            <h5 class="modal-title" id="addEventModalLabel">Create Schedule</h5>
            <button class="btn-close me-n1" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-x1">
            <div class="mb-3">
              <label class="fs-9" for="eventTitle">Title <span class="text-danger">*</span></label>
              <input class="form-control" id="eventTitle" type="text" name="title" required placeholder="Event title">
              <div class="invalid-feedback">Title is required.</div>
            </div>
            <div class="mb-3">
              <label class="fs-9" for="eventStartDate">Start Date <span class="text-danger">*</span></label>
              <input class="form-control datetimepicker" id="eventStartDate" type="text" name="startDate"
                placeholder="yyyy-mm-dd hh:mm"
                data-options='{"enableTime":true,"dateFormat":"Y-m-d H:i","time_24hr":true}'>
              <div class="invalid-feedback">Start date is required.</div>
            </div>
            <div class="mb-3">
              <label class="fs-9" for="eventEndDate">End Date</label>
              <input class="form-control datetimepicker" id="eventEndDate" type="text" name="endDate"
                placeholder="yyyy-mm-dd hh:mm (optional)"
                data-options='{"enableTime":true,"dateFormat":"Y-m-d H:i","time_24hr":true}'>
            </div>
            <div class="mb-3 form-check">
              <input class="form-check-input" type="checkbox" id="eventAllDay" name="allDay">
              <label class="form-check-label fs-9" for="eventAllDay">All Day</label>
            </div>
            <div class="mb-3">
              <label class="fs-9" for="eventDescription">Description</label>
              <textarea class="form-control" rows="3" id="eventDescription" name="description" placeholder="Optional description"></textarea>
            </div>
            <div class="mb-3">
              <label class="fs-9" for="eventLabel">Label</label>
              <select class="form-select" id="eventLabel" name="label">
                <option value="">None</option>
                <option value="primary">🔵 Business</option>
                <option value="danger">🔴 Important</option>
                <option value="success">🟢 Personal</option>
                <option value="warning">🟠 Must Attend</option>
              </select>
            </div>
            <!-- Alert area -->
            <div id="formAlert" class="alert py-2 d-none" role="alert"></div>
          </div>
          <div class="modal-footer d-flex justify-content-between align-items-center bg-body-tertiary border-0">
            <button class="btn btn-falcon-danger btn-sm d-none" type="button" id="btnDeleteEvent">
              <span class="fas fa-trash-alt me-1"></span>Delete
            </button>
            <div class="ms-auto">
              <button class="btn btn-falcon-default btn-sm me-2" type="button" data-bs-dismiss="modal">Cancel</button>
              <button class="btn btn-primary btn-sm px-4" type="submit" id="btnSaveEvent">
                <span class="fas fa-save me-1"></span>Save
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===================== EVENT DETAILS MODAL ===================== -->
  <div class="modal fade" id="eventDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border">
        <div class="modal-header px-x1 bg-body-tertiary border-bottom-0">
          <div>
            <h5 class="modal-title mb-0" id="detailModalTitle"></h5>
            <div id="detailCreatedBy" class="text-500 fs-10 mt-1"></div>
          </div>
          <button class="btn-close ms-auto me-n1" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-x1">
          <div id="detailLabelBadge" class="mb-3"></div>
          <!-- Description row -->
          <div class="event-detail-row" id="detailDescRow" style="display:none!important">
            <div class="detail-icon"><span class="fas fa-align-left"></span></div>
            <div class="detail-body">
              <div class="detail-label">Description</div>
              <div class="detail-value" id="detailDescription"></div>
            </div>
          </div>
          <!-- Date & Time row -->
          <div class="event-detail-row">
            <div class="detail-icon"><span class="fas fa-calendar-check"></span></div>
            <div class="detail-body">
              <div class="detail-label">Date and Time</div>
              <div class="detail-value" id="detailDateRange"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-body-tertiary border-0 py-2">
          <button class="btn btn-falcon-default btn-sm" type="button" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-falcon-primary btn-sm" type="button" id="btnEditFromDetail">
            <span class="fas fa-pencil-alt me-1"></span>Edit
          </button>
        </div>
      </div>
    </div>
  </div>

  <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/scripts.php'; ?>
  <script src="<?php echo BASE_URL; ?>/resources/vendors/fullcalendar/index.global.min.js"></script>

  <script>
  (function () {
    'use strict';

    const API_URL  = window.BASE_URL + '/api/calendar/';
    const CSRF     = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ----------------------------------------------------------------
    // Flatpickr init
    // ----------------------------------------------------------------
    document.querySelectorAll('.datetimepicker').forEach(function (el) {
      var opts = JSON.parse(el.dataset.options || '{}');
      flatpickr(el, opts);
      // Remove 'required' so native validation doesn't block flatpickr inputs
      el.removeAttribute('required');
    });

    // ----------------------------------------------------------------
    // View labels map
    // ----------------------------------------------------------------
    const viewTitles = {
      dayGridMonth : 'Month View',
      timeGridWeek : 'Week View',
      timeGridDay  : 'Day View',
      listWeek     : 'List View',
      listYear     : 'Year View',
    };

    // ----------------------------------------------------------------
    // FullCalendar init
    // ----------------------------------------------------------------
    var calendarEl = document.getElementById('tmsCalendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView            : 'dayGridMonth',
      headerToolbar          : false,
      height                 : 'auto',
      editable               : true,
      selectable             : true,
      selectMirror           : true,
      dayMaxEvents           : true,
      eventResizableFromStart: true,
      nowIndicator           : true,

      // Load events from API
      events: function (info, successCb, failureCb) {
        fetch(API_URL + '?start=' + info.startStr + '&end=' + info.endStr, {
          headers: { 'X-CSRF-Token': CSRF }
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            successCb(data.events.map(function (e) {
              return {
                id            : e.id,
                title         : e.title,
                start         : e.start,
                end           : e.end,
                allDay        : e.allDay,
                classNames    : e.label ? ['fc-event-' + e.label] : [],
                backgroundColor: e.backgroundColor || '',
                borderColor   : e.backgroundColor || '',
                extendedProps : {
                  description : e.description,
                  label       : e.label,
                },
              };
            }));
          } else { failureCb(); }
        })
        .catch(failureCb);
      },

      // Update title
      datesSet: function (info) {
        document.querySelector('.calendar-title').textContent = info.view.title;
      },

      // Click on blank date – open add form pre-filled
      dateClick: function (info) {
        openAddModal(info.dateStr);
      },

      // Select a range – open add form with start+end pre-filled
      select: function (info) {
        openAddModal(info.startStr, info.endStr);
      },

      // Drag-and-drop reschedule
      eventDrop: function (info) {
        saveEvent({
          event_id : info.event.id,
          start    : info.event.startStr,
          end      : info.event.endStr || null,
          allDay   : info.event.allDay,
        }, false, info.revert);
      },

      // Resize event (both ends thanks to eventResizableFromStart)
      eventResize: function (info) {
        saveEvent({
          event_id : info.event.id,
          start    : info.event.startStr,
          end      : info.event.endStr || null,
          allDay   : info.event.allDay,
        }, false, info.revert);
      },

      // Click on event – show details modal
      eventClick: function (info) {
        openDetailModal(info.event);
      },
    });

    calendar.render();

    // Sync title on init
    setTimeout(function () {
      document.querySelector('.calendar-title').textContent = calendar.view.title;
    }, 50);

    // ----------------------------------------------------------------
    // Toolbar controls
    // ----------------------------------------------------------------
    document.querySelectorAll('[data-event]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ev = this.dataset.event;
        if (ev === 'prev')  calendar.prev();
        if (ev === 'next')  calendar.next();
        if (ev === 'today') calendar.today();
      });
    });

    document.querySelectorAll('[data-fc-view]').forEach(function (item) {
      item.addEventListener('click', function (e) {
        e.preventDefault();
        var view = this.dataset.fcView;
        calendar.changeView(view);
        // Use viewTitles map (not textContent which includes checkmark icon text)
        document.querySelector('[data-view-title]').textContent = viewTitles[view] || 'Calendar';
        // Toggle active class (CSS handles checkmark visibility)
        document.querySelectorAll('[data-fc-view]').forEach(function (i) { i.classList.remove('active'); });
        this.classList.add('active');
        // Sync calendar title
        document.querySelector('.calendar-title').textContent = calendar.view.title;
      });
    });

    // ----------------------------------------------------------------
    // ADD / EDIT MODAL
    // ----------------------------------------------------------------
    var addModal = new bootstrap.Modal(document.getElementById('addEventModal'));
    var editingEventId = null;

    function openAddModal(dateStr, endStr) {
      editingEventId = null;
      document.getElementById('addEventModalLabel').textContent = 'Create Schedule';
      document.getElementById('addEventForm').reset();
      document.getElementById('eventId').value = '';
      document.getElementById('btnDeleteEvent').classList.add('d-none');
      clearFormAlert();
      var fpStart = document.getElementById('eventStartDate')._flatpickr;
      var fpEnd   = document.getElementById('eventEndDate')._flatpickr;
      if (fpStart) fpStart.clear();
      if (fpEnd)   fpEnd.clear();
      if (dateStr && fpStart) fpStart.setDate(dateStr, true);
      if (endStr  && fpEnd)   fpEnd.setDate(endStr,   true);
      addModal.show();
    }

    function openEditModal(event) {
      editingEventId = event.id;
      document.getElementById('addEventModalLabel').textContent = 'Edit Schedule';
      document.getElementById('eventId').value              = event.id;
      document.getElementById('eventTitle').value           = event.title || '';
      document.getElementById('eventDescription').value     = event.extendedProps.description || '';
      document.getElementById('eventLabel').value           = event.extendedProps.label || '';
      document.getElementById('eventAllDay').checked        = event.allDay;
      clearFormAlert();

      var fpStart = document.getElementById('eventStartDate')._flatpickr;
      var fpEnd   = document.getElementById('eventEndDate')._flatpickr;
      if (fpStart) { fpStart.clear(); if (event.start) fpStart.setDate(event.start, true); }
      if (fpEnd)   { fpEnd.clear();   if (event.end)   fpEnd.setDate(event.end,   true); }

      document.getElementById('btnDeleteEvent').classList.remove('d-none');
      addModal.show();
    }

    // Clear validation state + flatpickr on modal close
    document.getElementById('addEventModal').addEventListener('hidden.bs.modal', function () {
      document.getElementById('eventTitle').classList.remove('is-invalid');
      document.getElementById('eventStartDate').classList.remove('is-invalid');
      ['eventStartDate','eventEndDate'].forEach(function (id) {
        var fp = document.getElementById(id)._flatpickr;
        if (fp) fp.clear();
      });
    });

    // Clear invalid on input
    document.getElementById('eventTitle').addEventListener('input', function () { this.classList.remove('is-invalid'); });
    document.getElementById('eventStartDate').addEventListener('change', function () { this.classList.remove('is-invalid'); });

    // Save form submit
    document.getElementById('addEventForm').addEventListener('submit', function (e) {
      e.preventDefault();
      // Manual validation (flatpickr removes required attr)
      var title = document.getElementById('eventTitle').value.trim();
      var start = document.getElementById('eventStartDate').value.trim();
      if (!title) {
        document.getElementById('eventTitle').classList.add('is-invalid');
        return;
      }
      if (!start) {
        document.getElementById('eventStartDate').classList.add('is-invalid');
        return;
      }

      var payload = {
        title       : title,
        start       : start,
        end         : document.getElementById('eventEndDate').value || null,
        allDay      : document.getElementById('eventAllDay').checked,
        description : document.getElementById('eventDescription').value.trim(),
        label       : document.getElementById('eventLabel').value,
      };

      var eventId = document.getElementById('eventId').value;
      if (eventId) {
        payload.event_id = parseInt(eventId);
        saveEvent(payload, true);
      } else {
        createEvent(payload);
      }
    });

    // Delete button
    document.getElementById('btnDeleteEvent').addEventListener('click', function () {
      var eventId = document.getElementById('eventId').value;
      if (!eventId) return;
      if (!confirm('Delete this event?')) return;
      deleteEvent(parseInt(eventId));
    });

    // ----------------------------------------------------------------
    // DETAIL MODAL
    // ----------------------------------------------------------------
    var detailModal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
    var currentDetailEvent = null;

    function openDetailModal(event) {
      currentDetailEvent = event;
      document.getElementById('detailModalTitle').textContent = event.title;

      // Label badge
      var label    = event.extendedProps.label || '';
      var labelMap = {
        primary : { icon: '🔵', text: 'Business',    cls: 'primary' },
        danger  : { icon: '🔴', text: 'Important',   cls: 'danger'  },
        success : { icon: '🟢', text: 'Personal',    cls: 'success' },
        warning : { icon: '🟠', text: 'Must Attend', cls: 'warning' },
      };
      var badgeEl = document.getElementById('detailLabelBadge');
      badgeEl.innerHTML = (label && labelMap[label])
        ? '<span class="badge badge-subtle-' + labelMap[label].cls + ' fs-10">' +
          labelMap[label].icon + ' ' + labelMap[label].text + '</span>'
        : '';

      // Date & Time
      var start   = event.start ? formatDate(event.start, event.allDay) : '';
      var end     = event.end   ? formatDate(event.end,   event.allDay) : '';
      var rangeHtml = start;
      if (end && end !== start) rangeHtml += ' –<br>' + end;
      document.getElementById('detailDateRange').innerHTML = rangeHtml;

      // Description
      var desc    = event.extendedProps.description || '';
      var descRow = document.getElementById('detailDescRow');
      if (desc) {
        document.getElementById('detailDescription').textContent = desc;
        descRow.style.removeProperty('display');
      } else {
        descRow.style.setProperty('display', 'none', 'important');
      }

      detailModal.show();
    }

    document.getElementById('btnEditFromDetail').addEventListener('click', function () {
      detailModal.hide();
      setTimeout(function () { openEditModal(currentDetailEvent); }, 350);
    });

    // ----------------------------------------------------------------
    // API calls
    // ----------------------------------------------------------------
    function createEvent(payload) {
      setBtnLoading(true);
      fetch(API_URL, {
        method  : 'POST',
        headers : { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body    : JSON.stringify(payload),
      })
      .then(r => r.json())
      .then(function (data) {
        setBtnLoading(false);
        if (data.success) {
          addModal.hide();
          calendar.refetchEvents();
        } else {
          showFormAlert(data.message || 'Error saving event.', 'danger');
        }
      })
      .catch(function () { setBtnLoading(false); showFormAlert('Network error.', 'danger'); });
    }

    function saveEvent(payload, showModal, revertFn) {
      fetch(API_URL, {
        method  : 'PUT',
        headers : { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body    : JSON.stringify(payload),
      })
      .then(r => r.json())
      .then(function (data) {
        if (showModal) {
          setBtnLoading(false);
          if (data.success) { addModal.hide(); calendar.refetchEvents(); }
          else showFormAlert(data.message || 'Error updating event.', 'danger');
        } else {
          if (!data.success) { if (revertFn) revertFn(); else calendar.refetchEvents(); }
        }
      })
      .catch(function () {
        if (showModal) { setBtnLoading(false); showFormAlert('Network error.', 'danger'); }
        else if (revertFn) revertFn();
      });
    }

    function deleteEvent(eventId) {
      fetch(API_URL, {
        method  : 'DELETE',
        headers : { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body    : JSON.stringify({ event_id: eventId }),
      })
      .then(r => r.json())
      .then(function (data) {
        if (data.success) { addModal.hide(); calendar.refetchEvents(); }
        else showFormAlert(data.message || 'Error deleting event.', 'danger');
      })
      .catch(function () { showFormAlert('Network error.', 'danger'); });
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
    function setBtnLoading(loading) {
      var btn = document.getElementById('btnSaveEvent');
      btn.disabled = loading;
      btn.innerHTML = loading
        ? '<span class="spinner-border spinner-border-sm me-1"></span>Saving…'
        : '<span class="fas fa-save me-1"></span>Save';
    }

    function showFormAlert(msg, type) {
      var el = document.getElementById('formAlert');
      el.className = 'alert alert-' + type + ' py-2';
      el.textContent = msg;
    }

    function clearFormAlert() {
      var el = document.getElementById('formAlert');
      el.className = 'alert py-2 d-none';
      el.textContent = '';
      document.getElementById('addEventForm').classList.remove('was-validated');
    }

    function escHtml(str) {
      return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function formatDate(d, allDay) {
      if (!d) return '';
      var dt = new Date(d);
      if (allDay) return dt.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
      return dt.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
        + ' ' + dt.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
    }

  })();
  </script>

  <?php include dirname(dirname(dirname(__DIR__))) . '/admin/includes/body-top.php'; ?>
</body>
</html>

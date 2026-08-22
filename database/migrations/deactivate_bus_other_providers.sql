UPDATE ticket_providers
SET status = 'inactive'
WHERE provider_type IN ('bus', 'other');

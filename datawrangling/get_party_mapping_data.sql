-- 1. List "Broken" Organizations (Used by active Deputies/Senators)
SELECT 'BROKEN' as type, id, official_name, short_name 
FROM wp_politeia_political_organizations 
WHERE id IN (
    SELECT DISTINCT pm.organization_id 
    FROM wp_politeia_party_memberships pm
    JOIN wp_politeia_office_terms t ON t.person_id = pm.person_id
    WHERE t.ended_on IS NULL OR t.ended_on > NOW()
) AND (short_name IS NULL OR short_name = '')
UNION
-- 2. List Valid Parties (To use as reference for Short Names)
SELECT 'VALID' as type, id, official_name, short_name
FROM wp_politeia_political_organizations
WHERE type = 'PARTY' AND short_name IS NOT NULL AND short_name != '';

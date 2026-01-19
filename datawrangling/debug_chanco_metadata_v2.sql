-- DEBUG CHANCO METADATA (Corrected)
SELECT 
    j.official_name,
    j.type as jurisdiction_type,
    e.id as election_id,
    e.election_date,
    o.code as office_code,
    o.title as office_title,
    c.id as candidacy_id,
    c.elected
FROM wp_politeia_elections e
JOIN wp_politeia_offices o ON e.office_id = o.id
JOIN wp_politeia_candidacies c ON c.election_id = e.id
JOIN wp_politeia_jurisdictions j ON c.jurisdiction_id = j.id
WHERE j.official_name = 'CHANCO'
  AND YEAR(e.election_date) = 2024
  AND c.elected = 1;

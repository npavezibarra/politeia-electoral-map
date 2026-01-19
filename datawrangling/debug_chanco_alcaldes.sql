-- DEBUG CHANCO ALCALDE WINNERS
-- Listing the 4 "Winners" to see who they are.
SELECT 
    p.id as person_id,
    CONCAT(p.given_names, ' ', p.paternal_surname) as candidate_name,
    o.code as office_code,
    c.votes,
    c.elected,
    e.id as election_id
FROM wp_politeia_candidacies c
JOIN wp_politeia_people p ON c.person_id = p.id
JOIN wp_politeia_elections e ON c.election_id = e.id
JOIN wp_politeia_offices o ON e.office_id = o.id
JOIN wp_politeia_jurisdictions j ON c.jurisdiction_id = j.id
WHERE j.official_name = 'CHANCO'
  AND YEAR(e.election_date) = 2024
  AND o.code = 'ALCALDE'
  AND c.elected = 1;

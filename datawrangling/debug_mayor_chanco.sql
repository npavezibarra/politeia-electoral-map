-- DEBUG MAYOR OF CHANCO
-- Checking why the generic query missed him (does he have a broken URL?)
SELECT 
    j.official_name as comuna,
    CONCAT(p.given_names, ' ', p.paternal_surname) as alcalde,
    c.profile_photo_url,
    e.election_date,
    c.elected
FROM wp_politeia_jurisdictions j
JOIN wp_politeia_candidacies c ON c.jurisdiction_id = j.id
JOIN wp_politeia_elections e ON c.election_id = e.id
JOIN wp_politeia_people p ON c.person_id = p.id
WHERE j.official_name = 'CHANCO'
  AND c.elected = 1
ORDER BY e.election_date DESC;

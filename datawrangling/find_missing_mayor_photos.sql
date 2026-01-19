-- FIND MAYORS WITHOUT PHOTOS (2024 Term)
SELECT 
    j.official_name as comuna,
    CONCAT(p.given_names, ' ', p.paternal_surname) as alcalde,
    p.id as person_id,
    c.profile_photo_url
FROM wp_politeia_jurisdictions j
JOIN wp_politeia_candidacies c ON c.jurisdiction_id = j.id
JOIN wp_politeia_elections e ON c.election_id = e.id
JOIN wp_politeia_offices o ON e.office_id = o.id
JOIN wp_politeia_people p ON c.person_id = p.id
WHERE j.type = 'COMUNA'
  AND o.code = 'ALCALDE'
  AND c.elected = 1
  AND YEAR(e.election_date) = 2024
  AND (c.profile_photo_url IS NULL OR c.profile_photo_url = '')
ORDER BY j.official_name;

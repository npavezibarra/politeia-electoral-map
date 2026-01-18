-- Find Deputies without Party
SELECT 
    p.id,
    CONCAT(p.given_names, ' ', p.paternal_surname, ' ', COALESCE(p.maternal_surname, '')) AS full_name,
    t.started_on
FROM wp_politeia_office_terms t
JOIN wp_politeia_offices o ON t.office_id = o.id
JOIN wp_politeia_people p ON t.person_id = p.id
LEFT JOIN wp_politeia_party_memberships pm ON pm.person_id = p.id
LEFT JOIN wp_politeia_party_leanings pl ON pl.person_id = p.id
WHERE o.code = 'DIPUTADO'
  AND t.started_on >= '2022-03-11' -- Current Period
  AND pm.id IS NULL 
  AND pl.id IS NULL
ORDER BY p.paternal_surname;

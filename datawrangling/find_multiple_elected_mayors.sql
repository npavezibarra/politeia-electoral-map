-- FIND COMMUNES WITH MULTIPLE ELECTED MAYORS (2024)
SELECT 
    j.official_name,
    COUNT(c.id) as winner_count,
    GROUP_CONCAT(CONCAT(p.given_names, ' ', p.paternal_surname, ' (Votes: ', COALESCE(c.votes, 'NULL'), ')') SEPARATOR ' | ') as candidates
FROM wp_politeia_elections e
JOIN wp_politeia_offices o ON e.office_id = o.id
JOIN wp_politeia_candidacies c ON c.election_id = e.id
JOIN wp_politeia_jurisdictions j ON c.jurisdiction_id = j.id
JOIN wp_politeia_people p ON c.person_id = p.id
WHERE YEAR(e.election_date) = 2024
  AND o.code = 'ALCALDE'
  AND c.elected = 1
GROUP BY j.id
HAVING winner_count > 1;

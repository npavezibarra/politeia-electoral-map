-- Find Duplicate Politicians
-- Matches people with same surnames and overlapping first names
SELECT 
    p1.id as id_1, p1.given_names as name_1, 
    p2.id as id_2, p2.given_names as name_2,
    p1.paternal_surname, p1.maternal_surname,
    (SELECT COUNT(*) FROM wp_politeia_party_memberships WHERE person_id = p1.id) as mem_1,
    (SELECT COUNT(*) FROM wp_politeia_party_leanings WHERE person_id = p1.id) as lean_1,
    (SELECT COUNT(*) FROM wp_politeia_party_memberships WHERE person_id = p2.id) as mem_2,
    (SELECT COUNT(*) FROM wp_politeia_party_leanings WHERE person_id = p2.id) as lean_2
FROM wp_politeia_people p1
JOIN wp_politeia_people p2 ON 
    p1.paternal_surname = p2.paternal_surname 
    AND (p1.maternal_surname = p2.maternal_surname OR (p1.maternal_surname IS NULL AND p2.maternal_surname IS NULL))
    AND p1.id < p2.id
WHERE 
    (p1.given_names LIKE CONCAT('%', p2.given_names, '%') OR p2.given_names LIKE CONCAT('%', p1.given_names, '%'))
ORDER BY p1.paternal_surname;

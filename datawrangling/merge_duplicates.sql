-- MERGE DUPLICATES SCRIPT
-- 1. Identify pairs (Winner = Higher ID, Loser = Lower ID)
-- 2. Move data from Loser to Winner
-- 3. Delete Loser

START TRANSACTION;

-- Create temp table to store the merge plan
DROP TEMPORARY TABLE IF EXISTS tmp_merge_plan;
CREATE TEMPORARY TABLE tmp_merge_plan AS
SELECT 
    p1.id as loser_id, 
    p2.id as winner_id
FROM wp_politeia_people p1
JOIN wp_politeia_people p2 ON 
    p1.paternal_surname = p2.paternal_surname 
    AND (p1.maternal_surname = p2.maternal_surname OR (p1.maternal_surname IS NULL AND p2.maternal_surname IS NULL))
    AND p1.id < p2.id
WHERE 
    (p1.given_names LIKE CONCAT('%', p2.given_names, '%') OR p2.given_names LIKE CONCAT('%', p1.given_names, '%'));

-- Optional: View what will happen
-- SELECT * FROM tmp_merge_plan;

-- 1. Update Office Terms
UPDATE wp_politeia_office_terms t
JOIN tmp_merge_plan m ON t.person_id = m.loser_id
SET t.person_id = m.winner_id;

-- 2. Update Candidacies
UPDATE wp_politeia_candidacies c
JOIN tmp_merge_plan m ON c.person_id = m.loser_id
SET c.person_id = m.winner_id;

-- 3. Update Party Memberships
UPDATE wp_politeia_party_memberships pm
JOIN tmp_merge_plan m ON pm.person_id = m.loser_id
SET pm.person_id = m.winner_id;

-- 4. Update Party Leanings
UPDATE wp_politeia_party_leanings pl
JOIN tmp_merge_plan m ON pl.person_id = m.loser_id
SET pl.person_id = m.winner_id;

-- 5. Delete Losers
DELETE p
FROM wp_politeia_people p
JOIN tmp_merge_plan m ON p.id = m.loser_id;

-- Clean up
DROP TEMPORARY TABLE tmp_merge_plan;

COMMIT;

SELECT 'Merge Completed Successfully' as status;

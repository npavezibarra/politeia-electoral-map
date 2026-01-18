-- Find duplicates for the specific missing Senators
SELECT 
    p.id, 
    p.given_names, 
    p.paternal_surname, 
    p.maternal_surname,
    (SELECT COUNT(*) FROM wp_politeia_office_terms t WHERE t.person_id = p.id) as term_count,
    (SELECT COUNT(*) FROM wp_politeia_party_memberships pm WHERE pm.person_id = p.id) as membership_count,
    (SELECT COUNT(*) FROM wp_politeia_party_leanings pl WHERE pl.person_id = p.id) as leaning_count,
    -- Show linked party names if any
    (SELECT GROUP_CONCAT(COALESCE(org.short_name, org.official_name)) 
     FROM wp_politeia_party_memberships pm2 
     JOIN wp_politeia_political_organizations org ON pm2.organization_id = org.id 
     WHERE pm2.person_id = p.id) as parties
FROM wp_politeia_people p
WHERE 
   (p.paternal_surname = 'NUÑEZ' AND p.maternal_surname IN ('ARANCIBIA', 'URRUTIA'))
   OR (p.paternal_surname = 'SANHUEZA' AND p.maternal_surname = 'DUEÑAS')
   OR (p.paternal_surname = 'VELASQUEZ' AND p.maternal_surname = 'NUÑEZ')
ORDER BY p.paternal_surname, p.given_names;

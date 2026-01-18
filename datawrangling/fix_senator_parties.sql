-- FIX MISSING SENATOR PARTIES
-- Inserts missing memberships for specific Senators confirmed via CSV check.

INSERT INTO wp_politeia_party_memberships (person_id, organization_id, started_on, created_at, updated_at)
SELECT 
    p.id, 
    o.id, 
    '2022-03-11', 
    NOW(), 
    NOW()
FROM wp_politeia_people p
JOIN wp_politeia_political_organizations o ON (
    (p.paternal_surname = 'SANHUEZA' AND p.maternal_surname = 'DUEÑAS' AND o.official_name = 'UNION DEMOCRATA INDEPENDIENTE') OR
    (p.paternal_surname = 'NUÑEZ' AND p.maternal_surname = 'URRUTIA' AND o.official_name = 'RENOVACION NACIONAL') OR
    (p.paternal_surname = 'NUÑEZ' AND p.maternal_surname = 'ARANCIBIA' AND o.official_name = 'PARTIDO COMUNISTA DE CHILE') OR
    (p.paternal_surname = 'VELASQUEZ' AND p.maternal_surname = 'NUÑEZ' AND o.official_name = 'FEDERACION REGIONALISTA VERDE SOCIAL')
)
-- Only insert if not already there
WHERE NOT EXISTS (
    SELECT 1 FROM wp_politeia_party_memberships pm 
    WHERE pm.person_id = p.id AND pm.organization_id = o.id
);

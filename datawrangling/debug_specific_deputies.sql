-- Inspect specific deputies to debug missing party names
SELECT 
    p.id as person_id,
    p.given_names, 
    p.paternal_surname,
    -- Membership Check
    pm.id as membership_id,
    pm.organization_id as mem_org_id,
    po_mem.official_name as mem_org_name,
    po_mem.short_name as mem_org_short,
    -- Leaning Check
    pl.id as leaning_id,
    pl.organization_id as lean_org_id,
    po_lean.official_name as lean_org_name,
    po_lean.short_name as lean_org_short
FROM wp_politeia_people p
LEFT JOIN wp_politeia_party_memberships pm ON pm.person_id = p.id
LEFT JOIN wp_politeia_political_organizations po_mem ON po_mem.id = pm.organization_id
LEFT JOIN wp_politeia_party_leanings pl ON pl.person_id = p.id
LEFT JOIN wp_politeia_political_organizations po_lean ON po_lean.id = pl.organization_id
WHERE p.paternal_surname IN ('ARCE', 'CARTER', 'GAZMURI') 
  AND p.given_names REGEXP 'MONICA|ALVARO|ANA MARIA';

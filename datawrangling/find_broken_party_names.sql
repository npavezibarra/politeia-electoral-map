-- Find Deputies with "Invisible" Parties (Empty Short Names or Broken Links)
SELECT 
    p.id as person_id,
    CONCAT(p.given_names, ' ', p.paternal_surname) as person_name,
    
    -- Official Membership Info
    pm.organization_id as mem_org_id,
    po_mem.official_name as mem_full_name,
    po_mem.short_name as mem_short_name,

    -- Leaning Info
    pl.organization_id as lean_org_id,
    po_lean.official_name as lean_full_name,
    po_lean.short_name as lean_short_name

FROM wp_politeia_office_terms t
JOIN wp_politeia_offices o ON t.office_id = o.id
JOIN wp_politeia_people p ON t.person_id = p.id

-- Official Membership Join
LEFT JOIN wp_politeia_party_memberships pm ON pm.person_id = p.id
LEFT JOIN wp_politeia_political_organizations po_mem ON po_mem.id = pm.organization_id

-- Leaning Join
LEFT JOIN wp_politeia_party_leanings pl ON pl.person_id = p.id
LEFT JOIN wp_politeia_political_organizations po_lean ON po_lean.id = pl.organization_id

WHERE o.code = 'DIPUTADO'
  AND t.started_on >= '2022-03-11'
  -- Filter for PROBLEM cases:
  AND (
      -- Case A: Has membership but Short Name is Empty/Null
      (pm.id IS NOT NULL AND (po_mem.short_name IS NULL OR po_mem.short_name = ''))
      OR
      -- Case B: Has leaning but Short Name is Empty/Null
      (pl.id IS NOT NULL AND (po_lean.short_name IS NULL OR po_lean.short_name = ''))
      OR
      -- Case C: Has ID but Organization doesn't exist (Broken FK)
      (pm.id IS NOT NULL AND po_mem.id IS NULL)
      OR
      (pl.id IS NOT NULL AND po_lean.id IS NULL)
  )
ORDER BY p.paternal_surname;

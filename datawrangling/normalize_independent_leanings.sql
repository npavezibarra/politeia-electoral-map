-- NORMALIZE INDEPENDENT LEANINGS
-- 1. Insert LEANINGS for people who are currently members of "INDEPENDIENTE [PARTY]"
INSERT INTO wp_politeia_party_leanings (person_id, organization_id, type, created_at, updated_at)
SELECT 
    pm.person_id,
    real_org.id,
    'INDEPENDENT_SUPPORTED',
    NOW(),
    NOW()
FROM wp_politeia_party_memberships pm
JOIN wp_politeia_political_organizations fake_org ON pm.organization_id = fake_org.id
-- Match with Real Org by stripping "INDEPENDIENTE " prefix
JOIN wp_politeia_political_organizations real_org ON real_org.official_name = SUBSTRING(fake_org.official_name, 15)
WHERE fake_org.official_name LIKE 'INDEPENDIENTE %'
  AND fake_org.type = 'PARTY' -- Assuming these were imported as PARTY type
  -- Avoid duplicates if run multiple times
  AND NOT EXISTS (
      SELECT 1 FROM wp_politeia_party_leanings pl 
      WHERE pl.person_id = pm.person_id 
        AND pl.organization_id = real_org.id
  );

-- 2. Delete the "Fake" Memberships now that Leaning is recorded
DELETE pm
FROM wp_politeia_party_memberships pm
JOIN wp_politeia_political_organizations fake_org ON pm.organization_id = fake_org.id
JOIN wp_politeia_political_organizations real_org ON real_org.official_name = SUBSTRING(fake_org.official_name, 15)
WHERE fake_org.official_name LIKE 'INDEPENDIENTE %';

-- 3. (Optional) Cleanup "Fake" Organizations if they have no other members/leanings
-- We leave this commented out for safety unless explicitly requested to purge.
-- DELETE FROM wp_politeia_political_organizations WHERE official_name LIKE 'INDEPENDIENTE %' AND id NOT IN (SELECT organization_id FROM wp_politeia_party_memberships);

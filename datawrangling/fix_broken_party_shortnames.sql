-- FIX BROKEN PARTY SHORT NAMES
-- Updates missing short_names for 'Independent' parties so they appear on the frontend.

UPDATE wp_politeia_political_organizations
SET short_name = CASE
    -- Specific Acronym Mappings for major parties
    WHEN official_name = 'INDEPENDIENTE UNION DEMOCRATA INDEPENDIENTE' THEN 'Ind. UDI'
    WHEN official_name = 'INDEPENDIENTE RENOVACION NACIONAL' THEN 'Ind. RN'
    WHEN official_name = 'INDEPENDIENTE PARTIDO SOCIALISTA DE CHILE' THEN 'Ind. PS'
    WHEN official_name = 'INDEPENDIENTE PARTIDO POR LA DEMOCRACIA' THEN 'Ind. PPD'
    WHEN official_name = 'INDEPENDIENTE PARTIDO DEMOCRATA CRISTIANO' THEN 'Ind. DC'
    WHEN official_name = 'INDEPENDIENTE PARTIDO RADICAL DE CHILE' THEN 'Ind. PR'
    WHEN official_name = 'INDEPENDIENTE PARTIDO COMUNISTA DE CHILE' THEN 'Ind. PC'
    WHEN official_name = 'INDEPENDIENTE EVOLUCION POLITICA' THEN 'Ind. Evópoli'
    WHEN official_name = 'INDEPENDIENTE REVOLUCION DEMOCRATICA' THEN 'Ind. RD'
    WHEN official_name = 'INDEPENDIENTE CONVERGENCIA SOCIAL' THEN 'Ind. CS'
    WHEN official_name = 'INDEPENDIENTE COMUNES' THEN 'Ind. Comunes'
    WHEN official_name = 'INDEPENDIENTE PARTIDO LIBERAL DE CHILE' THEN 'Ind. PL'
    WHEN official_name = 'INDEPENDIENTE PARTIDO HUMANISTA' THEN 'Ind. PH'
    WHEN official_name = 'INDEPENDIENTE PARTIDO REPUBLICANO DE CHILE' THEN 'Ind. PLR'
    WHEN official_name = 'INDEPENDIENTE PARTIDO REGIONALISTA INDEPENDIENTE DEMOCRATA' THEN 'Ind. PRI'
    WHEN official_name = 'INDEPENDIENTE CIUDADANOS' THEN 'Ind. Ciudadanos'
    WHEN official_name = 'INDEPENDIENTE CENTRO UNIDO' THEN 'Ind. Centro Unido'
    WHEN official_name = 'PARTIDO RADICAL SOCIALDEMOCRATA' THEN 'PRSD'
    WHEN official_name = 'INDEPENDIENTES LISTA C' THEN 'IND-C'
    WHEN official_name = 'INDEPENDIENTES LISTA J' THEN 'IND-J'
    WHEN official_name = 'INDEPENDIENTES' THEN 'IND'
    WHEN official_name = 'INDEPENDIENTE' THEN 'IND'
    
    -- Generic Fallback: Replace 'INDEPENDIENTE ' with 'Ind. ' if no specific match
    WHEN official_name LIKE 'INDEPENDIENTE %' THEN REPLACE(official_name, 'INDEPENDIENTE ', 'Ind. ')
    
    -- Fallback: Use official name if all else fails
    ELSE official_name
END
WHERE short_name IS NULL OR short_name = '';

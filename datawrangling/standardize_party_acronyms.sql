-- STANDARDIZE PARTY ACRONYMS
-- Updates short_names to standard acronyms for cleaner display (e.g. "Ind. UDI" instead of "Ind. UNION DEMOCRATA INDEPENDIENTE")

UPDATE wp_politeia_political_organizations
SET short_name = CASE
    WHEN official_name = 'UNION DEMOCRATA INDEPENDIENTE' THEN 'UDI'
    WHEN official_name = 'RENOVACION NACIONAL' THEN 'RN'
    WHEN official_name = 'PARTIDO SOCIALISTA DE CHILE' THEN 'PS'
    WHEN official_name = 'PARTIDO POR LA DEMOCRACIA' THEN 'PPD'
    WHEN official_name = 'PARTIDO DEMOCRATA CRISTIANO' THEN 'DC'
    WHEN official_name = 'PARTIDO RADICAL DE CHILE' THEN 'PR'
    WHEN official_name = 'PARTIDO COMUNISTA DE CHILE' THEN 'PC'
    WHEN official_name = 'EVOLUCION POLITICA' THEN 'Evópoli'
    WHEN official_name = 'REVOLUCION DEMOCRATICA' THEN 'RD'
    WHEN official_name = 'CONVERGENCIA SOCIAL' THEN 'CS'
    WHEN official_name = 'COMUNES' THEN 'Comunes'
    WHEN official_name = 'PARTIDO LIBERAL DE CHILE' THEN 'PL'
    WHEN official_name = 'PARTIDO HUMANISTA' THEN 'PH'
    WHEN official_name = 'PARTIDO REPUBLICANO DE CHILE' THEN 'Republicanos'
    WHEN official_name = 'PARTIDO REGIONALISTA INDEPENDIENTE DEMOCRATA' THEN 'PRI'
    WHEN official_name = 'FEDERACION REGIONALISTA VERDE SOCIAL' THEN 'FRVS'
    WHEN official_name = 'PARTIDO DE LA GENTE' THEN 'PDG'
    WHEN official_name = 'PARTIDO ECOLOGISTA VERDE' THEN 'PEV'
    WHEN official_name = 'CENTRO UNIDO' THEN 'Centro Unido'
    WHEN official_name = 'CIUDADANOS' THEN 'Ciudadanos'
    WHEN official_name = 'AMPLITUD' THEN 'Amplitud'
    ELSE short_name
END
WHERE type = 'PARTY';

-- Update tournament format from Best Ball Scramble to Stableford
UPDATE tournaments
SET format = 'Stableford (Shotgun Start)'
WHERE format LIKE '%Best Ball%' OR format LIKE '%Scramble%';

-- Add barangay to business_account for partner address details
ALTER TABLE business_account
  ADD COLUMN business_barangay VARCHAR(120) NULL DEFAULT NULL AFTER business_city;

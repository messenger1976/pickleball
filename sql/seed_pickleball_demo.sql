-- Demo pickleball venue + courts (Phase 1 seed)

UPDATE business_account
SET business_name = 'Bohol Pickleball Club',
    business_subdomain = 'bohol-pickleball',
    business_status = 1
WHERE business_id = 2;

UPDATE business_page
SET page_tagline = 'Open play & court rentals in Tagbilaran',
    page_about = 'Community pickleball courts with indoor and outdoor options. Book your slot online and pay at the venue.',
    page_hours = 'Mon-Sun 6:00 AM - 10:00 PM',
    page_specialties = 'Open play, Court rental, Beginner-friendly',
    page_amenities = 'Parking, restrooms, water stations, lighting',
    page_published = 1,
    auto_confirm = 1,
    booking_max_days = 14,
    cancellation_note = 'Cancel at least 24 hours before your booking.'
WHERE business_id = 2;

INSERT INTO courts (business_id, court_name, court_type, court_surface, court_capacity, price_per_slot, slot_length_minutes, buffer_minutes, court_status)
SELECT 2, 'Court 1 — Outdoor', 'outdoor', 'hard', 4, 300.00, 60, 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM courts WHERE business_id=2 AND court_name='Court 1 — Outdoor');

INSERT INTO courts (business_id, court_name, court_type, court_surface, court_capacity, price_per_slot, slot_length_minutes, buffer_minutes, court_status)
SELECT 2, 'Court 2 — Indoor', 'indoor', 'wood', 4, 450.00, 60, 10, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM courts WHERE business_id=2 AND court_name='Court 2 — Indoor');

INSERT INTO courts (business_id, court_name, court_type, court_surface, court_capacity, price_per_slot, slot_length_minutes, buffer_minutes, court_status)
SELECT 3, 'Main Court', 'outdoor', 'concrete', 4, 250.00, 60, 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM courts WHERE business_id=3 AND court_name='Main Court');

UPDATE business_account SET business_name='Turbz Pickleball Hub', business_subdomain='turbz-pickleball' WHERE business_id=3;
UPDATE business_page
SET page_tagline='Friendly courts for casual and competitive play',
    page_about='Book outdoor courts by the hour. Pay at the venue.',
    page_hours='Daily 7:00 AM - 9:00 PM',
    page_specialties='Open play',
    page_published=1,
    auto_confirm=1,
    booking_max_days=14
WHERE business_id=3;

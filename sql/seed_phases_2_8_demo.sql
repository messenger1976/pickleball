-- Demo lessons + membership for venue 2
INSERT INTO coaches (business_id, coach_name, coach_bio, coach_status)
SELECT 2, 'Coach Ana', 'USAPA certified beginner & intermediate coach.', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM coaches WHERE business_id=2 AND coach_name='Coach Ana');

INSERT INTO lessons (business_id, coach_id, lesson_title, lesson_description, duration_minutes, price, capacity, lesson_status)
SELECT 2, (SELECT coach_id FROM coaches WHERE business_id=2 AND coach_name='Coach Ana' LIMIT 1),
       'Beginner Clinic', 'Fundamentals: dinks, serves, and kitchen rules.', 60, 600.00, 6, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM lessons WHERE business_id=2 AND lesson_title='Beginner Clinic');

INSERT INTO membership_plans (business_id, plan_name, plan_description, price, billing_period, discount_percent, plan_status)
SELECT 2, 'Club Member Monthly', 'Priority booking + 10% off court rentals.', 999.00, 'monthly', 10.00, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM membership_plans WHERE business_id=2 AND plan_name='Club Member Monthly');

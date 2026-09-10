-- Add profile_image column to users table for avatar upload support

ALTER TABLE users
    ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL
    AFTER language;

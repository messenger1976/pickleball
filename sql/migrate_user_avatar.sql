-- Avatar path for admin users (shown in sidebar / profile)
ALTER TABLE `user`
  ADD COLUMN user_avatar VARCHAR(255) NULL DEFAULT NULL AFTER user_email;

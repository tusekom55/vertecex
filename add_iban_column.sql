-- Users tablosuna IBAN sütunu ekleme
ALTER TABLE `users` ADD COLUMN `iban` VARCHAR(50) DEFAULT NULL AFTER `tc_no`;

-- Mevcut kullanıcılara örnek IBAN'lar ekleme
UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 44' WHERE `id` = 11;
UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 45' WHERE `id` = 12;
UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 46' WHERE `id` = 13;
UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 47' WHERE `id` = 6;

-- Test için diğer kullanıcılara da IBAN ekleyelim
UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 48' WHERE `id` = 1;
UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 49' WHERE `id` = 2;
UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 50' WHERE `id` = 3;
UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 51' WHERE `id` = 4;
UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 52' WHERE `id` = 5; 
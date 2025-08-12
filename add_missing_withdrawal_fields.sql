-- Para çekme talepleri tablosuna eksik alanları ekle
ALTER TABLE para_cekme_talepleri 
ADD COLUMN iban VARCHAR(50) AFTER tutar,
ADD COLUMN hesap_sahibi VARCHAR(100) AFTER iban;

-- Mevcut test verilerini güncelle
UPDATE para_cekme_talepleri 
SET iban = 'TR63 0006 4000 0019 3001 9751 45', 
    hesap_sahibi = 'Fatma Demir' 
WHERE id = 1;

UPDATE para_cekme_talepleri 
SET iban = 'TR63 0006 4000 0019 3001 9751 46', 
    hesap_sahibi = 'Ahmet Yılmaz' 
WHERE id = 2;

-- Yeni test verileri ekle
INSERT INTO para_cekme_talepleri (user_id, yontem, tutar, iban, hesap_sahibi, durum, aciklama) VALUES
(2, 'havale', 1500.00, 'TR63 0006 4000 0019 3001 9751 44', 'Ahmet Yılmaz', 'beklemede', 'Test para çekme talebi - Havale'),
(3, 'papara', 2500.00, '', 'Fatma Demir', 'beklemede', 'Test para çekme talebi - Papara');

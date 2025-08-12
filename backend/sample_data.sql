-- Örnek kullanıcılar
INSERT INTO users (username, email, password, role, balance, ad_soyad, telefon, tc_no, is_active) VALUES
('admin', 'admin@cryptotrading.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0, 'Sistem Yöneticisi', '+905551234567', '12345678901', TRUE),
('user1', 'user1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 5000.00, 'Ahmet Yılmaz', '+905551234568', '12345678902', TRUE),
('user2', 'user2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 7500.00, 'Fatma Demir', '+905551234569', '12345678903', TRUE),
('user3', 'user3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 3200.00, 'Mehmet Kaya', '+905551234570', '12345678904', TRUE);

-- Örnek para çekme talepleri
INSERT INTO para_cekme_talepleri (user_id, yontem, tutar, iban, hesap_sahibi, durum, tarih, aciklama) VALUES
(2, 'havale', 1500.00, 'TR63 0006 4000 0019 3001 9751 44', 'Ahmet Yılmaz', 'beklemede', NOW() - INTERVAL 2 DAY, 'Acil para ihtiyacı'),
(3, 'papara', 2500.00, '', 'Fatma Demir', 'beklemede', NOW() - INTERVAL 1 DAY, 'Alışveriş için'),
(4, 'havale', 800.00, 'TR63 0006 4000 0019 3001 9751 45', 'Mehmet Kaya', 'onaylandi', NOW() - INTERVAL 3 DAY, 'Fatura ödemesi'),
(2, 'papara', 1200.00, '', 'Ahmet Yılmaz', 'reddedildi', NOW() - INTERVAL 4 DAY, 'Yetersiz bakiye');

-- Örnek işlem geçmişi
INSERT INTO kullanici_islem_gecmisi (user_id, islem_tipi, islem_detayi, tutar, onceki_bakiye, sonraki_bakiye, tarih) VALUES
(2, 'para_yatirma', 'Kredi kartı ile yatırma', 2000.00, 3000.00, 5000.00, NOW() - INTERVAL 5 DAY),
(2, 'para_cekme', 'Para çekme onaylandı - havale', 1500.00, 5000.00, 3500.00, NOW() - INTERVAL 2 DAY),
(3, 'para_yatirma', 'Papara ile yatırma', 3000.00, 4500.00, 7500.00, NOW() - INTERVAL 3 DAY),
(4, 'para_yatirma', 'Havale ile yatırma', 1000.00, 2200.00, 3200.00, NOW() - INTERVAL 4 DAY),
(4, 'para_cekme', 'Para çekme onaylandı - havale', 800.00, 3200.00, 2400.00, NOW() - INTERVAL 3 DAY);

-- Örnek faturalar
INSERT INTO faturalar (user_id, islem_tipi, islem_id, fatura_no, tutar, kdv_orani, kdv_tutari, toplam_tutar, tarih) VALUES
(4, 'para_cekme', 3, 'FTR-20250111-0004-1234', 800.00, 18.00, 144.00, 944.00, NOW() - INTERVAL 3 DAY),
(2, 'para_cekme', 1, 'FTR-20250111-0002-5678', 1500.00, 18.00, 270.00, 1770.00, NOW() - INTERVAL 2 DAY);

-- Sistem ayarları
INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, aciklama) VALUES
('papara_numara', '+905551234567', 'Papara numarası'),
('banka_adi', 'Ziraat Bankası', 'Banka adı'),
('banka_iban', 'TR63 0006 4000 0019 3001 9751 44', 'Banka IBAN numarası'),
('banka_hesap_sahibi', 'Crypto Trading Platform', 'Banka hesap sahibi'),
('kart_komisyon_orani', '2.5', 'Kredi kartı komisyon oranı (%)'),
('papara_komisyon_orani', '1.0', 'Papara komisyon oranı (%)'),
('havale_komisyon_orani', '0.0', 'Havale komisyon oranı (%)'),
('minimum_cekme_tutari', '50', 'Minimum para çekme tutarı'),
('maksimum_cekme_tutari', '10000', 'Maksimum para çekme tutarı'),
('fatura_sirket_adi', 'Crypto Finance Ltd.', 'Fatura şirket adı'),
('fatura_adres', 'İstanbul, Türkiye', 'Fatura adresi'),
('fatura_vergi_no', '1234567890', 'Fatura vergi numarası'),
('fatura_telefon', '+90 212 555 0123', 'Fatura telefon numarası'),
('fatura_email', 'info@cryptofinance.com', 'Fatura email adresi');

-- Admin işlem logları
INSERT INTO admin_islem_loglari (admin_id, islem_tipi, hedef_id, islem_detayi, tarih) VALUES
(1, 'para_onaylama', 3, 'Para çekme onaylandı: 800.00 TL', NOW() - INTERVAL 3 DAY),
(1, 'para_onaylama', 1, 'Para çekme onaylandı: 1500.00 TL', NOW() - INTERVAL 2 DAY),
(1, 'para_onaylama', 4, 'Para çekme reddedildi: 1200.00 TL', NOW() - INTERVAL 4 DAY); 
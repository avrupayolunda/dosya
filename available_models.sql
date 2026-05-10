-- =====================================================
-- OpenAI → Gemini Migration SQL
-- Sadece UPDATE sorgulari - mevcut tablolari gunceller
-- =====================================================

-- 1. available_models tablosundaki enum'i guncelle
ALTER TABLE `available_models` 
  MODIFY `api_type` enum('gemini','deepseek') COLLATE utf8mb4_unicode_ci NOT NULL;

-- 2. Eski openai kayitlarini sil (varsa)
DELETE FROM `available_models` WHERE `api_type` = 'openai';

-- 3. Varsayilan Gemini modellerini ekle
INSERT INTO `available_models` (`api_type`, `model_id`, `model_name`, `is_active`) VALUES
('gemini', 'gemini-2.0-flash', 'Gemini 2.0 Flash', 1),
('gemini', 'gemini-2.5-pro-preview-05-06', 'Gemini 2.5 Pro', 1),
('gemini', 'gemini-2.5-flash-preview-04-17', 'Gemini 2.5 Flash', 1)
ON DUPLICATE KEY UPDATE `model_name` = VALUES(`model_name`), `is_active` = 1;

-- 4. ai_settings tablosundaki openai sutunlarini guncelle
-- (gemini_api_key zaten kullanici tarafindan olusturuldu)
ALTER TABLE `ai_settings` 
  CHANGE `openai_model` `gemini_model` varchar(100) DEFAULT 'gemini-2.0-flash';

-- 5. Agent tercihlerindeki 'openai' degerlerini 'gemini' olarak guncelle
UPDATE `ai_settings` SET 
  `research_agent` = 'gemini' WHERE `research_agent` = 'openai';
UPDATE `ai_settings` SET 
  `writer_agent` = 'gemini' WHERE `writer_agent` = 'openai';
UPDATE `ai_settings` SET 
  `critic_agent` = 'gemini' WHERE `critic_agent` = 'openai';
UPDATE `ai_settings` SET 
  `plan_creator_agent` = 'gemini' WHERE `plan_creator_agent` = 'openai';
UPDATE `ai_settings` SET 
  `plan_critic_agent` = 'gemini' WHERE `plan_critic_agent` = 'openai';

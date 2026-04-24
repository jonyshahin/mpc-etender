#!/usr/bin/env node
/**
 * Builds lang/ku.json from lang/en.json with:
 *  - Real Sorani (Central Kurdish) translations for keys used on the
 *    pre-auth public surface (welcome, staff auth, vendor auth pages).
 *  - `[en] <english>` fallback for every other key so Kurdish users see
 *    clearly-marked English on out-of-scope pages (dashboard, admin,
 *    vendor-portal-after-login) until those surfaces get translated.
 *
 * Keeps strict key-parity with en.json.
 * The [en] prefix is a QA signal — it makes untranslated coverage visible
 * in the UI rather than hiding as plain English.
 *
 * When adding a new key to en.json, add it to the SORANI map below if the
 * key renders on a pre-auth page. Otherwise the fallback handles it.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');

const EN_PATH = resolve(projectRoot, 'lang/en.json');
const KU_PATH = resolve(projectRoot, 'lang/ku.json');

/**
 * Sorani translation dictionary. Each entry is a key from en.json that
 * renders on the pre-auth public surface. Keys NOT in this map fall back
 * to `[en] <english>` in the output.
 *
 * Script: Perso-Arabic (Sorani uses Arabic script).
 * Register: procurement/construction, modern Sorani.
 * When Arabic loanwords are standard Iraqi-Kurdish procurement terms
 * (مناقەسە, دابینکەر), they are preferred over literal Kurdish coinages.
 */
const SORANI = {
    // ── welcome.* (new) ──────────────────────────────────────────────
    'welcome.head_title': 'MPC e-Tender — سەکۆی کڕینی دیجیتاڵی',
    'welcome.brand_title': 'MPC e-Tender',
    'welcome.brand_subtitle': 'سەکۆی کڕینی دیجیتاڵی',
    'welcome.staff_login': 'چوونەژوورەوەی ستاف',
    'welcome.vendor_portal': 'دەروازەی دابینکەران',
    'welcome.cta_go_to_dashboard': 'چوون بۆ داشبۆرد',
    'welcome.system_online': 'سیستەم چالاکە',
    'welcome.hero_line_1': 'بەڕێوەبردنی مناقەسە،',
    'welcome.hero_line_2': 'لە سەرەتاوە تا کۆتایی.',
    'welcome.hero_subhead':
        'سەکۆی کڕینی بیناسازی گروپی MPC — لە پێش‌مەرجی دابینکەرانەوە بۆ پێشکەشکردنی پێشنیاری داخراو، هەڵسەنگاندنی لیژنە، پەسەندکردنی چەند ئاستە، و ڕاگەیاندنی بڕیار.',
    'welcome.cta_register_vendor': 'تۆمارکردنی دابینکەر',
    'welcome.card_prequal_title': 'پێش‌مەرجی دابینکەران',
    'welcome.card_prequal_desc':
        'تۆمارکردنی دابینکەران بە بەڵگەنامە لەگەڵ پێداچوونەوەی بەڕێوەبەرایەتی و هاوتاکردنی بەپێی پۆل.',
    'welcome.card_tender_title': 'بەڕێوەبردنی مناقەسە',
    'welcome.card_tender_desc':
        'درووستکردنی مناقەسەی چەند قۆناغی لەگەڵ درووستکەری خشتەی بڕ، وەشانی بەڵگەنامە، پاشکۆ و ڕوونکردنەوە.',
    'welcome.card_sealed_title': 'پێشنیاری داخراو',
    'welcome.card_sealed_desc':
        'شاردنەوەی نرخی پێشنیار بە AES-256 تا ڕۆژی کردنەوە. پێویستی بە دەستووری دوو کەسە بۆ کردنەوە.',
    'welcome.card_committee_title': 'هەڵسەنگاندنی لیژنە',
    'welcome.card_committee_desc':
        'لیژنەی تەکنیکی و دارایی بە جیاوازی نرخ دەدەن. پشتگیری سیستەمی دوو ئەمباڵۆ.',
    'welcome.card_approval_title': 'زنجیرەی پەسەندکردن',
    'welcome.card_approval_desc':
        'زنجیرەی پەسەندکردنی چەند ئاستە بەپێی بەها، لەگەڵ وەکالەت و بەرزکردنەوەی ئۆتۆماتیکی.',
    'welcome.card_audit_title': 'چاودێری و یاسایی',
    'welcome.card_audit_desc':
        'تۆماری چاودێری زیادکراو، تۆماری دەستگەیشتنی بەڵگەنامە، و پشتگیری تەواوی دووزمانی (EN/AR).',
    'welcome.footer_copyright': '© :year گروپی MPC. هەموو مافەکان پارێزراون.',
    'welcome.footer_tagline': 'MPC e-Tender · سەکۆی کڕینی دیجیتاڵی',

    // ── auth.staff_* (new) ────────────────────────────────────────────
    'auth.staff_login_title': 'چوونەژوورەوە بۆ هەژمارەکەت',
    'auth.staff_login_description':
        'ئیمەیڵ و وشەی نهێنیت لە خوارەوە بنووسە بۆ چوونەژوورەوە',
    'auth.staff_forgot_password_title': 'وشەی نهێنیت لەبیر چووە',
    'auth.staff_forgot_password_description':
        'ئیمەیڵەکەت بنووسە بۆ وەرگرتنی بەستەری گۆڕینی وشەی نهێنی',
    'auth.staff_reset_password_title': 'گۆڕینی وشەی نهێنی',
    'auth.staff_reset_password_description':
        'تکایە وشەی نهێنی نوێ لە خوارەوە بنووسە',
    'auth.staff_register_title': 'درووستکردنی هەژمار',
    'auth.staff_register_description':
        'زانیارییەکانت لە خوارەوە بنووسە بۆ درووستکردنی هەژمار',
    'auth.staff_verify_email_title': 'پشتڕاستکردنەوەی ئیمەیڵ',
    'auth.staff_verify_email_description':
        'تکایە ئیمەیڵەکەت پشتڕاست بکەرەوە بە کرتەکردن لەسەر ئەو بەستەرەی بۆمان ناردیت.',
    'auth.staff_confirm_password_title': 'پشتڕاستکردنەوەی وشەی نهێنی',
    'auth.staff_confirm_password_description':
        'ئەمە بەشێکی پارێزراوی سیستەمەکەیە. تکایە پێش بەردەوامبوون وشەی نهێنیت پشتڕاست بکەرەوە.',

    // ── auth.* (used on pre-auth pages) ───────────────────────────────
    'auth.vendor_portal': 'دەروازەی دابینکەران',
    'auth.vendor_login': 'چوونەژوورەوەی دابینکەر',
    'auth.vendor_register': 'تۆمارکردنی دابینکەر',
    'auth.vendor_registration': 'تۆمارکردنی دابینکەر',
    'auth.sign_in': 'چوونەژوورەوە',
    'auth.sign_in_description': 'بۆ چوونەژوورەوەی هەژمارەکەت',
    'auth.sign_up': 'خۆتۆمارکردن',
    'auth.signing_in': 'چوونەژوورەوە...',
    'auth.log_in': 'چوونەژوورەوە',
    'auth.log_out': 'چوونەدەرەوە',
    'auth.email_address': 'ناونیشانی ئیمەیڵ',
    'auth.password': 'وشەی نهێنی',
    'auth.confirm_password': 'پشتڕاستکردنەوەی وشەی نهێنی',
    'auth.forgot_password': 'وشەی نهێنیت لەبیر چووە؟',
    'auth.remember_me': 'لەبیرم بهێنەرەوە',
    'auth.no_account': 'هەژمارت نیە؟',
    'auth.register_here': 'لێرە تۆمار بکە',
    'auth.already_have_account': 'هەژمارت هەیە؟',
    'auth.create_account': 'درووستکردنی هەژمار',
    'auth.reset_password': 'گۆڕینی وشەی نهێنی',
    'auth.update_password': 'نوێکردنەوەی وشەی نهێنی',
    'auth.email_reset_link': 'ناردنی بەستەری گۆڕینی وشەی نهێنی',
    'auth.or_return_to': 'یان بگەڕێوە بۆ',
    'auth.verification_link_sent': 'بەستەری پشتڕاستکردنەوە نێردرا.',
    'auth.resend_verification': 'ناردنەوەی ئیمەیڵی پشتڕاستکردنەوە',
    'auth.registration_description':
        'کۆمپانیاکەت تۆمار بکە بۆ بەشداریکردن لە مناقەسەکان.',
    'auth.enter_company_details': 'زانیارییەکانی کۆمپانیاکەت بنووسە',
    'auth.provide_contact_details': 'زانیارییەکانی پەیوەندیدار بنووسە',
    'auth.select_at_least_one_category': 'لانیکەم یەک پۆل هەڵبژێرە.',
    'auth.selected_categories': 'پۆلە هەڵبژێردراوەکان',
    'auth.categories_selected': 'پۆل هەڵبژێردراوە',
    'auth.review_your_information': 'زانیارییەکانت پێداچوونەوە بکە',
    'auth.verify_details_before_submitting':
        'پێش ناردن زانیارییەکانت پشتڕاست بکەرەوە.',
    'auth.ready_to_submit': 'ئامادەیت بۆ ناردن؟',
    'auth.registration_review_message':
        'تکایە زانیارییەکانت پێش ناردن پێداچوونەوە بکە.',
    'auth.fix_errors': 'تکایە هەڵەکانی خوارەوە چارەسەر بکە:',
    'auth.step_company_info': 'زانیاری کۆمپانیا',
    'auth.step_contact_person': 'کەسی پەیوەندیدار',
    'auth.step_categories': 'پۆلەکان',
    'auth.step_review': 'پێداچوونەوە',
    'auth.step_submit': 'ناردن',
    'auth.two_factor_auth': 'پشتڕاستکردنەوەی دوو فاکتەری',

    // ── form.* (auth forms) ───────────────────────────────────────────
    'form.email': 'ئیمەیڵ',
    'form.password': 'وشەی نهێنی',
    'form.new_password': 'وشەی نهێنی نوێ',
    'form.confirm_password': 'پشتڕاستکردنەوەی وشەی نهێنی',
    'form.name': 'ناو',
    'form.phone': 'ژمارەی تەلەفۆن',
    'form.address': 'ناونیشان',
    'form.city': 'شار',
    'form.country': 'وڵات',
    'form.website': 'ماڵپەڕ',
    'form.company_name': 'ناوی کۆمپانیا',
    'form.company_name_ar': 'ناوی کۆمپانیا (بە عەرەبی)',
    'form.trade_license_no': 'ژمارەی مۆڵەتی بازرگانی',
    'form.contact_person': 'کەسی پەیوەندیدار',
    'form.whatsapp_number': 'ژمارەی واتساپ',
    'form.forgot_password': 'وشەی نهێنیت لەبیر چووە؟',
    'form.send_reset_link': 'ناردنی بەستەری گۆڕین',
    'form.email_your_registered_email': 'ئیمەیڵی تۆمارکراوت بنووسە',

    // ── btn.* (common pre-auth buttons) ──────────────────────────────
    'btn.submit': 'ناردن',
    'btn.submitting': 'لە ناردن...',
    'btn.cancel': 'هەڵوەشاندنەوە',
    'btn.previous': 'پێشوو',
    'btn.next': 'دواتر',
    'btn.back': 'گەڕانەوە',
    'btn.back_to_login': 'گەڕانەوە بۆ چوونەژوورەوە',
    'btn.sending': 'لە ناردن...',
    'btn.saving': 'لە پاشەکەوتکردن...',
    'btn.save': 'پاشەکەوتکردن',
    'btn.submit_registration': 'ناردنی تۆمارکردن',
    'btn.reset_password': 'گۆڕینی وشەی نهێنی',
    'btn.confirm': 'پشتڕاستکردنەوە',

    // ── page.vendor_* ─────────────────────────────────────────────────
    'page.vendor_forgot_password_title': 'وشەی نهێنیت لەبیر چووە',
    'page.vendor_forgot_password_desc':
        'ئەو ئیمەیڵەی پێی تۆمارت کردووە بنووسە، بەستەری گۆڕینی وشەی نهێنیت بۆ دەنێرین.',
    'page.vendor_reset_password_title': 'وشەی نهێنی نوێ دابنێ',
    'page.vendor_reset_password_desc':
        'وشەی نهێنی بەهێزی نوێ بۆ هەژماری دابینکەری خۆت هەڵبژێرە.',
    'page.vendor_change_password_title': 'گۆڕینی وشەی نهێنی',
    'page.vendor_change_password_desc':
        'بۆ پاراستنی هەژمارەکەت وشەی نهێنی گۆڕەوە.',

    // ── vendor.* (used in auth flow) ─────────────────────────────────
    'vendor.company_information': 'زانیارییەکانی کۆمپانیا',
    'vendor.contact_person': 'کەسی پەیوەندیدار',
    'vendor.select_categories_description':
        'پۆلە پیشەییەکانی کۆمپانیاکەت هەڵبژێرە',

    // ── pages.vendor.* (auth-context) ────────────────────────────────
    'pages.vendor.business_categories': 'پۆلە بازرگانییەکان',
};

// Build ku.json
const enRaw = readFileSync(EN_PATH, 'utf8');
const en = JSON.parse(enRaw);

const ku = {};
let translated = 0;
let fallback = 0;

for (const [key, enValue] of Object.entries(en)) {
    if (key in SORANI) {
        ku[key] = SORANI[key];
        translated++;
    } else {
        ku[key] = `[en] ${enValue}`;
        fallback++;
    }
}

// Preserve en.json structure (pretty-print with 4-space indent to match)
writeFileSync(KU_PATH, JSON.stringify(ku, null, 4) + '\n', 'utf8');

console.log(`Wrote ${KU_PATH}`);
console.log(`  Keys translated to Sorani: ${translated}`);
console.log(`  Keys carrying [en] fallback: ${fallback}`);
console.log(`  Total: ${translated + fallback}`);
console.log(`  en.json total: ${Object.keys(en).length}`);
console.log(
    `  Parity: ${translated + fallback === Object.keys(en).length ? 'OK' : 'MISMATCH'}`,
);

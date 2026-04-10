<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Tender Published
            ['slug' => 'tender_published_email', 'channel' => 'email', 'notification_type' => 'tender_published', 'subject_en' => 'New Tender: {{tender_reference}}', 'subject_ar' => 'مناقصة جديدة: {{tender_reference}}', 'body_template_en' => "A new tender \"{{tender_title}}\" has been published for project {{project_name}}.\n\nSubmission deadline: {{deadline}}\n\nPlease log in to the portal to view details.", 'body_template_ar' => "تم نشر مناقصة جديدة \"{{tender_title}}\" لمشروع {{project_name}}.\n\nآخر موعد للتقديم: {{deadline}}\n\nيرجى تسجيل الدخول للبوابة لعرض التفاصيل."],
            ['slug' => 'tender_published_inapp', 'channel' => 'in_app', 'notification_type' => 'tender_published', 'subject_en' => 'New Tender Published', 'subject_ar' => 'تم نشر مناقصة جديدة', 'body_template_en' => 'Tender {{tender_reference}} "{{tender_title}}" is now open for bids.', 'body_template_ar' => 'المناقصة {{tender_reference}} "{{tender_title}}" مفتوحة الآن للعروض.'],
            ['slug' => 'tender_published_whatsapp', 'channel' => 'whatsapp', 'notification_type' => 'tender_published', 'subject_en' => 'New Tender', 'subject_ar' => 'مناقصة جديدة', 'body_template_en' => 'New tender {{tender_reference}} published. Deadline: {{deadline}}', 'body_template_ar' => 'مناقصة جديدة {{tender_reference}}. آخر موعد: {{deadline}}', 'whatsapp_template_name' => 'tender_published'],

            // Deadline Reminder
            ['slug' => 'deadline_reminder_email', 'channel' => 'email', 'notification_type' => 'deadline_reminder', 'subject_en' => 'Deadline Reminder: {{tender_reference}}', 'subject_ar' => 'تذكير بالموعد النهائي: {{tender_reference}}', 'body_template_en' => "Reminder: The submission deadline for tender {{tender_reference}} \"{{tender_title}}\" is {{deadline}}.\n\n{{days_remaining}} days remaining.", 'body_template_ar' => "تذكير: الموعد النهائي لتقديم العروض للمناقصة {{tender_reference}} \"{{tender_title}}\" هو {{deadline}}.\n\nمتبقي {{days_remaining}} أيام."],
            ['slug' => 'deadline_reminder_inapp', 'channel' => 'in_app', 'notification_type' => 'deadline_reminder', 'subject_en' => 'Deadline Approaching', 'subject_ar' => 'الموعد النهائي يقترب', 'body_template_en' => '{{days_remaining}} days left for tender {{tender_reference}}.', 'body_template_ar' => 'متبقي {{days_remaining}} أيام للمناقصة {{tender_reference}}.'],

            // Bid Received
            ['slug' => 'bid_received_inapp', 'channel' => 'in_app', 'notification_type' => 'bid_received', 'subject_en' => 'Bid Received', 'subject_ar' => 'تم استلام عرض', 'body_template_en' => 'New bid received from {{vendor_name}} for tender {{tender_reference}}.', 'body_template_ar' => 'تم استلام عرض جديد من {{vendor_name}} للمناقصة {{tender_reference}}.'],
            ['slug' => 'bid_received_email', 'channel' => 'email', 'notification_type' => 'bid_received', 'subject_en' => 'Bid Received: {{tender_reference}}', 'subject_ar' => 'تم استلام عرض: {{tender_reference}}', 'body_template_en' => "A new bid has been submitted by {{vendor_name}} for tender {{tender_reference}}.\n\nTotal bids: {{total_bids}}", 'body_template_ar' => "تم تقديم عرض جديد من {{vendor_name}} للمناقصة {{tender_reference}}.\n\nإجمالي العروض: {{total_bids}}"],

            // Bid Opened
            ['slug' => 'bid_opened_inapp', 'channel' => 'in_app', 'notification_type' => 'bid_opened', 'subject_en' => 'Bids Opened', 'subject_ar' => 'تم فتح العروض', 'body_template_en' => 'Bids for tender {{tender_reference}} have been opened. {{bid_count}} bids received.', 'body_template_ar' => 'تم فتح العروض للمناقصة {{tender_reference}}. تم استلام {{bid_count}} عرض.'],

            // Award Notification
            ['slug' => 'award_winner_email', 'channel' => 'email', 'notification_type' => 'award_notification', 'subject_en' => 'Congratulations — Award Notification', 'subject_ar' => 'تهانينا — إشعار ترسية', 'body_template_en' => "Dear {{vendor_name}},\n\nCongratulations! You have been awarded tender {{tender_reference}} \"{{tender_title}}\".\n\nAward amount: {{award_amount}} {{currency}}\n\nPlease log in to view the award letter.", 'body_template_ar' => "عزيزي {{vendor_name}}،\n\nتهانينا! تمت ترسية المناقصة {{tender_reference}} \"{{tender_title}}\" عليكم.\n\nمبلغ الترسية: {{award_amount}} {{currency}}\n\nيرجى تسجيل الدخول لعرض خطاب الترسية."],
            ['slug' => 'award_winner_whatsapp', 'channel' => 'whatsapp', 'notification_type' => 'award_notification', 'subject_en' => 'Award Notification', 'subject_ar' => 'إشعار ترسية', 'body_template_en' => 'Congratulations! You have been awarded tender {{tender_reference}}. Amount: {{award_amount}} {{currency}}', 'body_template_ar' => 'تهانينا! تمت ترسية المناقصة {{tender_reference}} عليكم. المبلغ: {{award_amount}} {{currency}}', 'whatsapp_template_name' => 'award_notification'],
            ['slug' => 'award_other_email', 'channel' => 'email', 'notification_type' => 'award_notification', 'subject_en' => 'Tender Award Decision: {{tender_reference}}', 'subject_ar' => 'قرار ترسية المناقصة: {{tender_reference}}', 'body_template_en' => "Dear {{vendor_name}},\n\nThe tender {{tender_reference}} \"{{tender_title}}\" has been awarded to another bidder.\n\nThank you for your participation.", 'body_template_ar' => "عزيزي {{vendor_name}}،\n\nتمت ترسية المناقصة {{tender_reference}} \"{{tender_title}}\" على مقدم عرض آخر.\n\nشكراً لمشاركتكم."],

            // Approval Required
            ['slug' => 'approval_required_inapp', 'channel' => 'in_app', 'notification_type' => 'approval_required', 'subject_en' => 'Approval Required', 'subject_ar' => 'مطلوب موافقة', 'body_template_en' => 'Approval required for tender {{tender_reference}} (Level {{approval_level}}). Deadline: {{deadline}}.', 'body_template_ar' => 'مطلوب موافقة على المناقصة {{tender_reference}} (المستوى {{approval_level}}). الموعد النهائي: {{deadline}}.'],
            ['slug' => 'approval_required_email', 'channel' => 'email', 'notification_type' => 'approval_required', 'subject_en' => 'Approval Required: {{tender_reference}}', 'subject_ar' => 'مطلوب موافقة: {{tender_reference}}', 'body_template_en' => "Your approval is required for tender {{tender_reference}} \"{{tender_title}}\".\n\nApproval Level: {{approval_level}}\nRecommended Vendor: {{vendor_name}}\nAmount: {{award_amount}} {{currency}}\nDeadline: {{deadline}}", 'body_template_ar' => "مطلوب موافقتكم على المناقصة {{tender_reference}} \"{{tender_title}}\".\n\nمستوى الموافقة: {{approval_level}}\nالمقاول الموصى به: {{vendor_name}}\nالمبلغ: {{award_amount}} {{currency}}\nالموعد النهائي: {{deadline}}"],

            // Document Expiry
            ['slug' => 'document_expiry_email', 'channel' => 'email', 'notification_type' => 'document_expiry', 'subject_en' => 'Document Expiring Soon', 'subject_ar' => 'مستند على وشك الانتهاء', 'body_template_en' => "Your document \"{{document_title}}\" will expire on {{expiry_date}}.\n\nPlease upload an updated version.", 'body_template_ar' => "مستندكم \"{{document_title}}\" سينتهي في {{expiry_date}}.\n\nيرجى رفع نسخة محدثة."],
            ['slug' => 'document_expiry_inapp', 'channel' => 'in_app', 'notification_type' => 'document_expiry', 'subject_en' => 'Document Expiring', 'subject_ar' => 'مستند على وشك الانتهاء', 'body_template_en' => 'Document "{{document_title}}" expires on {{expiry_date}}.', 'body_template_ar' => 'المستند "{{document_title}}" ينتهي في {{expiry_date}}.'],

            // Addendum Issued
            ['slug' => 'addendum_issued_email', 'channel' => 'email', 'notification_type' => 'addendum_issued', 'subject_en' => 'Addendum Issued: {{tender_reference}}', 'subject_ar' => 'ملحق صادر: {{tender_reference}}', 'body_template_en' => "Addendum #{{addendum_number}} has been issued for tender {{tender_reference}}.\n\nSubject: {{addendum_subject}}\n\nPlease review the updated tender documents.", 'body_template_ar' => "تم إصدار ملحق #{{addendum_number}} للمناقصة {{tender_reference}}.\n\nالموضوع: {{addendum_subject}}\n\nيرجى مراجعة وثائق المناقصة المحدثة."],
            ['slug' => 'addendum_issued_inapp', 'channel' => 'in_app', 'notification_type' => 'addendum_issued', 'subject_en' => 'Addendum Issued', 'subject_ar' => 'ملحق صادر', 'body_template_en' => 'Addendum #{{addendum_number}} issued for {{tender_reference}}.', 'body_template_ar' => 'ملحق #{{addendum_number}} صادر للمناقصة {{tender_reference}}.'],

            // Evaluation Complete
            ['slug' => 'evaluation_complete_inapp', 'channel' => 'in_app', 'notification_type' => 'evaluation_complete', 'subject_en' => 'Evaluation Complete', 'subject_ar' => 'اكتمال التقييم', 'body_template_en' => 'Evaluation for tender {{tender_reference}} is complete. Report ready for review.', 'body_template_ar' => 'اكتمل تقييم المناقصة {{tender_reference}}. التقرير جاهز للمراجعة.'],
        ];

        foreach ($templates as $t) {
            NotificationTemplate::updateOrCreate(
                ['slug' => $t['slug']],
                array_merge($t, ['is_active' => true])
            );
        }
    }
}

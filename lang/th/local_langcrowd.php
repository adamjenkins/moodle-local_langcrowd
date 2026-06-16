<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Thai language strings for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 hama.history@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Core.
$string['pluginname'] = 'การแปลภาษาแบบร่วมสร้าง';

// Privacy.
$string['privacy:metadata'] = 'ปลั๊กอินการแปลภาษาแบบร่วมสร้างจัดเก็บคะแนนและข้อเสนอแนะที่ผู้ใช้ส่งมาเพื่อช่วยสร้างชุดภาษาของชุมชน';
$string['privacy:metadata:local_langcrowd_votes'] = 'บันทึกคะแนนของผู้ใช้แต่ละคน (อนุมัติหรือปฏิเสธ) สำหรับสตริงภาษาแต่ละรายการ';
$string['privacy:metadata:local_langcrowd_votes:userid'] = 'รหัสของผู้ใช้ที่ลงคะแนน';
$string['privacy:metadata:local_langcrowd_votes:vote'] = 'ค่าคะแนน: 1 สำหรับอนุมัติ, -1 สำหรับปฏิเสธ';
$string['privacy:metadata:local_langcrowd_votes:timecreated'] = 'เวลาที่บันทึกคะแนน';
$string['privacy:metadata:local_langcrowd_suggestions'] = 'บันทึกคำแปลทางเลือกที่ผู้ใช้เสนอ';
$string['privacy:metadata:local_langcrowd_suggestions:userid'] = 'รหัสของผู้ใช้ที่ส่งข้อเสนอแนะ';
$string['privacy:metadata:local_langcrowd_suggestions:suggestion'] = 'ข้อความคำแปลทางเลือกที่เสนอ';
$string['privacy:metadata:local_langcrowd_suggestions:timecreated'] = 'เวลาที่ส่งข้อเสนอแนะ';

// Admin settings.
$string['settings'] = 'การตั้งค่าการแปลภาษาแบบร่วมสร้าง';
$string['settings_enabled'] = 'เปิดใช้งานการแปลแบบร่วมสร้าง';
$string['settings_enabled_desc'] = 'เปิดหรือปิดการใช้งานอินเทอร์เฟซการลงคะแนนบนหน้า Moodle ทั้งหมด หมายเหตุ: ต้องกำหนดค่าตัวจัดการสตริงแบบกำหนดเองใน config.php ด้วยเพื่อให้การทำงานของการแปลทำงานได้';
$string['settings_threshold'] = 'เกณฑ์การอนุมัติ';
$string['settings_threshold_desc'] = 'จำนวนคะแนนอนุมัติที่ต้องการเพื่อล็อกสตริงที่แปลแล้ว';
$string['settings_maxstrings'] = 'จำนวนสตริงสูงสุดต่อหน้า';
$string['settings_maxstrings_desc'] = 'จำนวนสตริงสูงสุดที่สามารถมีปุ่มลงคะแนนได้ในหน้าเดียว เพิ่มสำหรับหน้าผู้ดูแลระบบที่ซับซ้อน ลดเพื่อจำกัดความยุ่งเหยิงของอินเทอร์เฟซและขนาดข้อมูล ค่าเริ่มต้น: 5000';
$string['settings_showmode'] = 'โหมดการแสดงปุ่ม';
$string['settings_showmode_desc'] = 'กำหนดเวลาที่ปุ่มลงคะแนน (ถูก/ผิด) จะแสดงข้างๆ สตริงที่แปลแล้ว';
$string['settings_showmode_hover'] = 'แสดงเมื่อเลื่อนเมาส์เท่านั้น';
$string['settings_showmode_always'] = 'แสดงตลอดเวลา';
$string['settings_allowed_roles'] = 'บทบาทที่ได้รับอนุญาตให้ลงคะแนน';
$string['settings_allowed_roles_desc'] = 'เลือกบทบาทที่สามารถเห็นปุ่มลงคะแนนและส่งคะแนนหรือข้อเสนอแนะได้ เว้นว่างเพื่ออนุญาตให้ผู้ใช้ที่ตรวจสอบสิทธิ์แล้วทุกคน ผู้ใช้ต้องมีบทบาทนั้นในบริบทใดก็ได้ (ระบบ, หมวดหมู่, หลักสูตร ฯลฯ)';
$string['settings_showadminlink'] = 'แสดงลิงก์ผู้ดูแลระบบในแถบนำทาง';
$string['settings_showadminlink_desc'] = 'เมื่อเปิดใช้งาน ลิงก์ "การแปลภาษาแบบร่วมสร้าง" จะปรากฏในแถบนำทางหลักทางด้านขวาของ "การดูแลระบบเว็บไซต์" มองเห็นได้เฉพาะผู้ดูแลระบบเท่านั้น';
$string['settings_highlightcolor'] = 'สีไฮไลต์สตริง';
$string['settings_highlightcolor_desc'] = 'สีพื้นหลังที่ใช้กับสตริงเมื่อเลื่อนเมาส์ไปที่ปุ่มลงคะแนน ใช้ค่าสีเลขฐานสิบหกที่ถูกต้อง';
$string['settings_allowed_langs'] = 'ภาษาที่เปิดใช้งานการแปลแบบร่วมสร้าง';
$string['settings_allowed_langs_desc'] = 'เลือกชุดภาษาที่ติดตั้งแล้วที่ควรเปิดใช้งานอินเทอร์เฟซการลงคะแนน เว้นว่างเพื่อเปิดใช้งานสำหรับทุกภาษา ผู้ใช้ที่ภาษาอินเทอร์เฟซไม่อยู่ในรายการนี้จะไม่เห็นปุ่มลงคะแนน';
$string['settings_configphp_notice'] = 'เพื่อเปิดใช้งานการแปลสตริง ให้เพิ่มบรรทัดต่อไปนี้ในไฟล์ config.php ของคุณ:';
$string['settings_stringmanager_warning'] = 'คำเตือน: ตัวจัดการสตริงแบบกำหนดเองไม่ได้ทำงาน การแปลสตริงจะไม่ทำงานจนกว่าจะนำการเปลี่ยนแปลง config.php ไปใช้';
$string['settings_stringmanager_active'] = 'ตัวจัดการสตริงแบบกำหนดเองทำงานอยู่';

// Navigation / report links.
$string['report_approved'] = 'สตริงที่ได้รับการอนุมัติ';
$string['report_suggestions'] = 'ข้อเสนอแนะของผู้ใช้';
$string['export'] = 'ส่งออกชุดภาษา';

// Report table columns.
$string['col_component'] = 'คอมโพเนนต์';
$string['col_stringkey'] = 'รหัสสตริง';
$string['col_sourcevalue'] = 'ต้นฉบับภาษาอังกฤษ';
$string['col_currentvalue'] = 'การแปล';
$string['col_votecount'] = 'คะแนน';
$string['col_datelocked'] = 'วันที่ล็อก';
$string['col_submittedby'] = 'ส่งโดย';
$string['col_date'] = 'วันที่';
$string['col_actions'] = 'การดำเนินการ';
$string['col_status'] = 'สถานะ';
$string['col_suggestion'] = 'การแปลที่เสนอ';

// Report actions.
$string['action_unlock'] = 'ปลดล็อก';
$string['action_promote'] = 'อนุมัติ';
$string['action_push'] = 'ส่งเข้าชุดภาษา';
$string['action_reject'] = 'ปฏิเสธ';
$string['action_unlock_confirm'] = 'คุณแน่ใจหรือไม่ว่าต้องการปลดล็อกสตริงนี้และรีเซ็ตจำนวนคะแนน?';
$string['action_promote_confirm'] = 'คุณแน่ใจหรือไม่ว่าต้องการอนุมัติข้อเสนอแนะนี้? มันจะกลายเป็นการแปลที่ใช้งานทันทีและรีเซ็ตคะแนนเป็นศูนย์';
$string['action_push_confirm'] = 'ส่งข้อเสนอแนะนี้เข้าชุดภาษาที่ใช้งานอยู่? มันจะถูกใช้เป็นคำแปลทันทีในขณะที่การลงคะแนนของชุมชนยังดำเนินต่อ มันจะล็อกโดยอัตโนมัติเมื่อถึงเกณฑ์คะแนน';
$string['action_reject_confirm'] = 'คุณแน่ใจหรือไม่ว่าต้องการปฏิเสธข้อเสนอแนะนี้?';

// Export page.
$string['export_language'] = 'ภาษา';
$string['export_components'] = 'คอมโพเนนต์';
$string['export_components_desc'] = 'เลือกคอมโพเนนต์ที่ต้องการส่งออก เว้นว่างเพื่อส่งออกทั้งหมด';
$string['export_scope'] = 'ขอบเขต';
$string['export_scope_locked'] = 'เฉพาะสตริงที่ล็อกแล้ว';
$string['export_scope_all'] = 'สตริงทั้งหมดที่มีการแปล';
$string['export_download'] = 'ดาวน์โหลดชุดภาษา';
$string['export_nodata'] = 'ไม่พบสตริงที่ตรงกับเกณฑ์ที่เลือก';

// Voting buttons.
$string['btn_approve'] = 'อนุมัติการแปลนี้';
$string['btn_suggest'] = 'เสนอทางเลือก';
$string['modal_suggest_title'] = 'เสนอการแปลทางเลือก';
$string['modal_original_label'] = 'การแปลปัจจุบัน';
$string['modal_suggestion_label'] = 'การแปลที่คุณเสนอ';
$string['modal_submit'] = 'ส่งข้อเสนอแนะ';
$string['modal_cancel'] = 'ยกเลิก';
$string['vote_thanks'] = 'ขอบคุณสำหรับคะแนนของคุณ';
$string['suggestion_thanks'] = 'ขอบคุณสำหรับข้อเสนอแนะของคุณ';

// Status labels.
$string['status_pending'] = 'รอดำเนินการ';
$string['status_locked'] = 'ล็อกแล้ว';
$string['status_pushed'] = 'ส่งเข้าชุดภาษาแล้ว';
$string['status_promoted'] = 'ส่งเสริมแล้ว';
$string['status_rejected'] = 'ปฏิเสธแล้ว';

// Filters.
$string['filter_language'] = 'ภาษา';
$string['filter_component'] = 'คอมโพเนนต์';
$string['filter_apply'] = 'นำตัวกรองไปใช้';
$string['filter_all'] = 'ทั้งหมด';

// Scheduled task.
$string['task_aggregate_votes'] = 'รวบรวมคะแนนจากการร่วมสร้าง';

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
 * Japanese language strings for local_langcrowd.
 *
 * @package    local_langcrowd
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_lock'] = 'ロック';
$string['action_lock_confirm'] = 'この文字列を投票のしきい値を無視して、現在の翻訳として今すぐロックしますか?';
$string['action_promote'] = '承認';
$string['action_promote_confirm'] = 'この提案を承認してもよろしいですか? 直ちに有効な翻訳となり、投票数がゼロにリセットされます。';
$string['action_push'] = '言語パックに適用';
$string['action_push_confirm'] = 'この提案を有効な言語パックに適用しますか? コミュニティの投票を継続したまま、直ちに翻訳として提供されます。投票のしきい値に達すると自動的にロックされます。';
$string['action_reject'] = '却下';
$string['action_reject_confirm'] = 'この提案を却下してもよろしいですか?';
$string['action_unlock'] = '削除';
$string['action_unlock_confirm'] = 'この文字列のロックを解除して投票数をリセットしてもよろしいですか?';
$string['btn_approve'] = 'この翻訳を承認する';
$string['btn_suggest'] = '代替案を提案する';
$string['bulk_apply'] = '適用';
$string['bulk_approved'] = '{$a} 件の提案を承認しました。';
$string['bulk_confirm'] = '選択した行にこの操作を適用しますか?';
$string['bulk_locked'] = '{$a} 件の文字列をロックしました。';
$string['bulk_pushed'] = '{$a} 件の提案を適用しました。';
$string['bulk_rejected'] = '{$a} 件の提案を却下しました。';
$string['bulk_removed'] = '{$a} 件の文字列を削除しました。';
$string['bulk_with_selected'] = '選択した項目に対して';
$string['col_actions'] = '操作';
$string['col_component'] = 'コンポーネント';
$string['col_currentvalue'] = '翻訳';
$string['col_date'] = '日付';
$string['col_datelocked'] = 'ロック日';
$string['col_sourcevalue'] = '英語の原文';
$string['col_status'] = 'ステータス';
$string['col_stringkey'] = '文字列キー';
$string['col_submittedby'] = '投稿者';
$string['col_suggestion'] = '提案された翻訳';
$string['col_votecount'] = '投票数';
$string['export'] = '言語パックのエクスポート';
$string['export_all_languages'] = 'すべての言語';
$string['export_components'] = 'コンポーネント';
$string['export_components_desc'] = 'エクスポートするコンポーネントを選択してください。空欄の場合はすべてエクスポートします。';
$string['export_download'] = '言語パックをダウンロード';
$string['export_language'] = '言語';
$string['export_nodata'] = '選択した条件に一致する文字列がありません。';
$string['export_scope'] = '範囲';
$string['export_scope_all'] = '翻訳のあるすべての文字列';
$string['export_scope_locked'] = 'ロックされた文字列のみ';
$string['filter_all'] = 'すべて';
$string['filter_apply'] = 'フィルタを適用';
$string['filter_component'] = 'コンポーネント';
$string['filter_language'] = '言語';
$string['filter_showzero'] = '投票のない文字列を含める';
$string['filter_status'] = 'ステータス';
$string['modal_cancel'] = 'キャンセル';
$string['modal_original_label'] = '現在の翻訳';
$string['modal_source_label'] = '英語の原文';
$string['modal_submit'] = '提案を送信';
$string['modal_suggest_title'] = '代替翻訳を提案する';
$string['modal_suggestion_label'] = 'あなたの提案する翻訳';
$string['overlay_progress_label'] = '承認';
$string['overlay_toggle'] = '翻訳を改善する';
$string['overlay_undo'] = '元に戻す';
$string['overview'] = '概要';
$string['overview_norecentsuggestions'] = '保留中の提案はありません。';
$string['overview_nostrings'] = 'まだ文字列が記録されていません。クラウドソーシングを有効にしてサイトを閲覧すると、文字列の収集が始まります。';
$string['overview_notopvoted'] = 'まだ投票のある文字列はありません。';
$string['overview_pendingsuggestions'] = '保留中の提案';
$string['overview_recentsuggestions'] = '最近の提案';
$string['overview_topvoted'] = '最も投票の多い文字列';
$string['overview_totalstrings'] = '追跡中の文字列';
$string['pluginname'] = '言語クラウドソーシング';
$string['privacy:metadata'] = '言語クラウドソーシングプラグインは、コミュニティ言語パックの構築を支援するためにユーザーが送信した投票と提案を保存します。';
$string['privacy:metadata:local_langcrowd_suggestions'] = 'ユーザーが提案した代替翻訳を記録します。';
$string['privacy:metadata:local_langcrowd_suggestions:suggestion'] = '提案された代替翻訳のテキスト。';
$string['privacy:metadata:local_langcrowd_suggestions:timecreated'] = '提案が送信された日時。';
$string['privacy:metadata:local_langcrowd_suggestions:userid'] = '提案を送信したユーザーのID。';
$string['privacy:metadata:local_langcrowd_votes'] = '個々の言語文字列に対する各ユーザーの投票 (承認または却下) を記録します。';
$string['privacy:metadata:local_langcrowd_votes:timecreated'] = '投票が記録された日時。';
$string['privacy:metadata:local_langcrowd_votes:userid'] = '投票したユーザーのID。';
$string['privacy:metadata:local_langcrowd_votes:vote'] = '投票値: 承認は1、却下は-1。';
$string['report_approved'] = '承認済み文字列';
$string['report_suggestions'] = 'ユーザーの提案';
$string['report_voting'] = '投票レポート';
$string['select_all'] = 'すべて選択';
$string['settings'] = '言語クラウドソーシングの設定';
$string['settings_adminvote_locks'] = '管理者の承認投票で即座にロック';
$string['settings_adminvote_locks_desc'] = '有効にすると、サイト管理者が投じた承認投票は、通常の投票しきい値を無視して直ちに文字列をロックします。';
$string['settings_allowed_components'] = 'クラウドソーシングを有効にするコンポーネント';
$string['settings_allowed_components_desc'] = '投票オーバーレイを有効にするコンポーネント (例: mod_forum、core) を選択してください。空欄の場合はすべてのコンポーネントで有効になります。リストはこれまでに検出された文字列から作成されます。選択をクリアすると、新しいコンポーネントが再び検出されるようになります。';
$string['settings_allowed_langs'] = 'クラウドソーシングを有効にする言語';
$string['settings_allowed_langs_desc'] = '投票オーバーレイを有効にするインストール済み言語パックを選択してください。空欄の場合はすべての言語で有効になります。インターフェース言語がこのリストにないユーザーには投票ボタンが表示されません。';
$string['settings_allowed_roles'] = '投票を許可するロール';
$string['settings_allowed_roles_desc'] = '投票ボタンを表示し、投票や提案を送信できるロールを選択してください。空欄の場合は認証済みのすべてのユーザーに許可されます。ユーザーは任意のコンテキスト (システム、カテゴリ、コースなど) でそのロールを持っている必要があります。';
$string['settings_configphp_notice'] = '文字列の注釈を有効にするには、config.php ファイルに次の行を追加してください:';
$string['settings_enabled'] = 'クラウドソーシングを有効にする';
$string['settings_enabled_desc'] = 'すべての Moodle ページでクラウドソーシングオーバーレイを有効または無効にします。注意: 文字列の注釈を機能させるには、config.php でカスタム文字列マネージャも設定する必要があります。';
$string['settings_forcetranslate'] = '翻訳モードを常にオンにする';
$string['settings_forcetranslate_desc'] = '有効にすると、フローティングの「翻訳を改善する」トグルが非表示になり、利用できるすべてのユーザーに対して投票オーバーレイが常にオンになります。無効の場合、ユーザーはトグルで自分でオンにします。';
$string['settings_highlightcolor'] = '文字列のハイライト色';
$string['settings_highlightcolor_desc'] = '投票ボタンにマウスを合わせたときに文字列に適用される背景色。有効な16進数の色の値を使用してください。';
$string['settings_maxstrings'] = 'ページごとの最大文字列数';
$string['settings_maxstrings_desc'] = '1ページで投票ボタンを表示できる文字列の最大数。複雑な管理ページでは増やし、UIの煩雑さやペイロードサイズを抑えるには減らします。デフォルト: 5000。';
$string['settings_showadminlink'] = 'ナビゲーションバーに管理リンクを表示';
$string['settings_showadminlink_desc'] = '有効にすると、「言語クラウドソーシング」リンクがプライマリナビゲーションバーの「サイト管理」の右側に表示されます。サイト管理者のみに表示されます。';
$string['settings_showmode'] = 'ボタンの表示モード';
$string['settings_showmode_always'] = '常に表示';
$string['settings_showmode_desc'] = '翻訳された文字列の横にチェック/バツの投票ボタンをいつ表示するかを制御します。';
$string['settings_showmode_hover'] = 'マウスオーバー時のみ表示';
$string['settings_stringmanager_active'] = 'カスタム文字列マネージャが有効です。';
$string['settings_stringmanager_warning'] = '警告: カスタム文字列マネージャが有効になっていません。config.php の変更を適用するまで、文字列の注釈は機能しません。';
$string['settings_threshold'] = '承認しきい値';
$string['settings_threshold_desc'] = '翻訳された文字列をロックするために必要な承認投票の数。';
$string['status_locked'] = 'ロック済み';
$string['status_pending'] = '保留中';
$string['status_promoted'] = '採用済み';
$string['status_pushed'] = '適用済み';
$string['status_rejected'] = '却下済み';
$string['suggestion_thanks'] = 'ご提案ありがとうございます。';
$string['task_aggregate_votes'] = 'クラウドソーシング投票の集計';
$string['vote_thanks'] = '投票ありがとうございます。';

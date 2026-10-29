<?php

namespace Modules\Import\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Import\Actions\CheckImport;
use Modules\Import\Actions\RunImport;
use Modules\Import\Actions\StageImport;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Imports in the demo, the way an academy coming from sheets starts: a
 * teachers' sheet already brought in (two teachers made, one row with a
 * problem to fix), and a families' sheet checked and waiting for the
 * import button, so the review step can be tried.
 */
final class DemoImportSeeder extends Seeder
{
    public function run(StageImport $stage, CheckImport $check, RunImport $import): void
    {
        $owner = DB::table('users')->where('email', 'owner@demo.test')->value('id');
        if ($owner === null) {
            return;
        }
        app()->setLocale('ar');      // what the owner reads; each row's verdict is kept in the importer's language

        $teachers = $stage->handle('teachers', $this->sheet([
            ['الاسم', 'الهاتف', 'البريد الإلكتروني', 'الجنس', 'البلد', 'المواد', 'لغة الشرح', 'حصص تجريبية'],
            ['أ. هبة الله سمير', '01023456789', 'heba@demo.test', 'أنثى', 'مصر', 'قرآن · حفظ، تجويد', 'عربي', 'نعم'],
            ['أ. يوسف القاضي', '0551234567', '', 'ذكر', 'الرياض', 'لغة عربية، نحو', 'عربي، إنجليزي', 'لا'],
            ['أ. ريهام', '', '', 'أنثى', 'مصر', 'إنجليزي', '', ''],
        ]), 'معلمو-الأكاديمية.xlsx', $owner);
        $import->handle($check->handle($teachers, $owner), $owner);

        $families = $stage->handle('families', $this->sheet([
            ['اسم الطالب', 'تاريخ الميلاد', 'البلد', 'ولي الأمر', 'هاتف ولي الأمر', 'صلة القرابة', 'بريد الطالب الإلكتروني'],
            ['آدم محمد', '2017', 'الكويت', 'محمد الشمري', '+96550001111', 'أب', ''],
            ['لين محمد', '2014-09-02', 'الكويت', 'محمد الشمري', '+96550001111', 'أب', ''],
            ['عبد الرحمن', '1995', 'ألمانيا', '', '', '', 'abdo@example.com'],
            ['تالا', '', 'دبي', 'منى', '', 'أم', ''],
        ]), 'طلاب-الفصل-الجديد.xlsx', $owner);
        $check->handle($families, $owner);
    }

    /** @param  list<list<string>>  $rows */
    private function sheet(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'demo-import-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }
}

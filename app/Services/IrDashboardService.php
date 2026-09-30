<?php
declare(strict_types=1);

namespace App\Services;

final class IrDashboardService
{
    public static function achievement(?float $actual, ?float $target, ?float $baseline, string $method, string $direction): ?float
    {
        return PdoDashboardService::achievement($actual,$target,$baseline,$method,$direction);
    }

    public static function rows(array $records): array
    {
        return array_map(static function(array $row): array {
            $actual=$row['actual_value']===null?null:(float)$row['actual_value'];
            $target=$row['target_value']===null?null:(float)$row['target_value'];
            $baseline=$row['baseline_value']===null?null:(float)$row['baseline_value'];
            $method=(string)($row['calculation_method']??'ratio');
            $direction=(string)($row['direction']??'higher');
            $achievement=self::achievement($actual,$target,$baseline,$method,$direction);
            $exceeded=!empty($row['allow_overachievement'])?(float)($row['exceeded_threshold']??110):null;
            return [
                'id'=>(int)$row['id'],'type'=>'IR','code'=>(string)$row['code'],
                'parentCode'=>$row['parent_code']??null,'component'=>(string)$row['component'],
                'name'=>(string)$row['name'],'definition'=>(string)($row['definition']??''),
                'unit'=>(string)$row['unit'],'baseline'=>$baseline,'year'=>$row['fiscal_year']??null,
                'period'=>$row['reporting_period']??null,'state'=>$row['state']??null,
                'target'=>$target,'result'=>$actual,'achievement'=>$achievement,
                'visualAchievement'=>$achievement===null?null:min((float)($row['achievement_cap']??100),$achievement),
                'status'=>PdoDashboardService::status($achievement,(float)($row['on_track_threshold']??90),$exceeded),
                'calculationMethod'=>$method,'direction'=>$direction,
                'reportingBasis'=>(string)($row['reporting_basis']??'cumulative'),
                'frequency'=>(string)($row['frequency']??''),'reportedAt'=>$row['reported_at']??null,
            ];
        },$records);
    }
}

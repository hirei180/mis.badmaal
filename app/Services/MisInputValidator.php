<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\MisRepository;
use InvalidArgumentException;

final class MisInputValidator
{
    public const YEARS=['FY25','FY26','FY27','FY28','FY29','FY30'];
    public const PERIODS=['Annual','Q1','Q2','Q3','Q4'];
    public const STATES=['National','Banadir','Puntland','Galmudug','Hirshabelle','South West State','Jubaland'];
    public const COMPONENTS=['Component 1','Component 2','Component 3'];

    private static function choice(array $d,string $key,array $choices): string
    {
        if (!in_array($d[$key]??null,$choices,true)) throw new InvalidArgumentException('Select a valid '.str_replace('_',' ',$key).'.');
        return $d[$key];
    }
    private static function number(array $d,string $key,bool $nullable=false): ?float
    {
        $value=$d[$key]??null;
        if ($nullable && ($value===''||$value===null)) return null;
        if (!is_scalar($value)||!is_numeric($value)||!is_finite((float)$value)||(float)$value<0||(float)$value>99999999999999.99) throw new InvalidArgumentException('Enter a valid non-negative '.str_replace('_',' ',$key).'.');
        return round((float)$value,2);
    }
    private static function text(array $d,string $key,int $max,bool $required=false): string
    {
        $v=$d[$key]??'';
        if (!is_string($v)||mb_strlen($v)>$max||($required&&trim($v)==='')) throw new InvalidArgumentException('Enter a valid '.str_replace('_',' ',$key).'.');
        return trim($v);
    }
    private static function date(array $d,string $key): string
    {
        $v=$d[$key]??'';
        if ($v==='') return '';
        if (!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)||\DateTimeImmutable::createFromFormat('!Y-m-d',$v)?->format('Y-m-d')!==$v) throw new InvalidArgumentException('Enter a valid '.str_replace('_',' ',$key).'.');
        return $v;
    }
    public static function indicator(array $d): void
    {
        self::number($d,'target_value',true); self::number($d,'actual_value',true); self::text($d,'notes',10000);
    }
    public static function contract(array $d): void
    {
        self::text($d,'reference_no',120,true); self::text($d,'title',500,true); self::text($d,'contractor',255); self::text($d,'notes',10000);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',trim($d['reference_no']))) throw new InvalidArgumentException('Use letters, numbers, dots, slashes, underscores or hyphens for the contract reference.');
        self::choice($d,'component',self::COMPONENTS); self::choice($d,'state',self::STATES);
        self::choice($d,'status',['planned','procurement','active','completed','suspended','cancelled']);
        foreach(['contract_value','paid_amount','progress_percent'] as $key) self::number($d,$key);
        if ((float)$d['progress_percent']>100) throw new InvalidArgumentException('Progress must be between 0 and 100.');
        if ((float)$d['paid_amount']>(float)$d['contract_value']) throw new InvalidArgumentException('Paid amount cannot exceed contract value.');
        self::date($d,'start_date'); self::date($d,'end_date');
        if (!empty($d['start_date'])&&!empty($d['end_date'])&&$d['end_date']<$d['start_date']) throw new InvalidArgumentException('End date must follow start date.');
    }
    public static function finance(array $d): void
    {
        self::choice($d,'fiscal_year',self::YEARS); self::choice($d,'quarter',self::PERIODS);
        self::choice($d,'component',self::COMPONENTS);
        foreach(['budget_amount','committed_amount','disbursed_amount','expenditure_amount'] as $key) self::number($d,$key);
        self::text($d,'notes',10000);
    }
    public static function normalize(string $module,array $d,MisRepository $repo): array
    {
        if ($module==='contracts') {
            self::contract($d);
            $out=[];
            foreach(['reference_no'=>120,'title'=>500,'contractor'=>255,'notes'=>10000] as $key=>$max) $out[$key]=self::text($d,$key,$max);
            foreach(['contract_value','paid_amount','progress_percent'] as $key) $out[$key]=self::number($d,$key);
            foreach(['component','state','status'] as $key) $out[$key]=$d[$key];
            foreach(['start_date','end_date'] as $key) $out[$key]=self::date($d,$key);
            return $out;
        }
        if ($module==='finance') {
            self::finance($d); $out=[];
            foreach(['fiscal_year','quarter','component'] as $key) $out[$key]=$d[$key];
            foreach(['budget_amount','committed_amount','disbursed_amount','expenditure_amount'] as $key) $out[$key]=self::number($d,$key);
            $out['notes']=self::text($d,'notes',10000); return $out;
        }
        if ($module==='framework') {
            $baseline=self::number($d,'baseline_value'); $date=self::date($d,'baseline_date');
            if (!$baseline||!$date||$date>date('Y-m-d')) throw new InvalidArgumentException('Provide a positive baseline and a reference date that is not in the future.');
            return ['baseline_value'=>$baseline,'baseline_date'=>$date,'baseline_source'=>self::text($d,'baseline_source',255,true)];
        }
        if ($module!=='indicators') throw new InvalidArgumentException('Unknown module.');
        self::indicator($d);
        $id=filter_var($d['indicator_id']??null,FILTER_VALIDATE_INT);
        $i=$id?$repo->indicatorById($id):null;
        if (!$i) throw new InvalidArgumentException('Select an active dashboard indicator.');
        $fy=self::choice($d,'fiscal_year',self::YEARS);$period=self::choice($d,'reporting_period',self::PERIODS);$state=self::choice($d,'state',self::STATES);
        $actual=self::number($d,'actual_value',true);$target=self::number($d,'target_value',true)??$repo->indicatorTargetForYear($id,$fy);
        $date=self::date($d,'reported_at');
        if ($date>date('Y-m-d')||($actual!==null&&$date==='')) throw new InvalidArgumentException('Actual results require a reporting date that is not in the future.');
        foreach(['target'=>$target,'actual'=>$actual] as $label=>$value) {
            if($value===null)continue;
            if($i['unit']==='Number'&&floor($value)!==$value)throw new InvalidArgumentException('This indicator counts whole units; enter a whole '.$label.'.');
            if($i['unit']==='Percentage'&&$value>100)throw new InvalidArgumentException('Percentage values must be between 0 and 100.');
        }
        if ($i['calculation_method']==='reduction_from_baseline'&&$actual!==null&&(float)$i['baseline_value']<=0) throw new InvalidArgumentException('An approved positive baseline is required before reporting this result.');
        $achievement=PdoDashboardService::achievement($actual,$target,$i['baseline_value']===null?null:(float)$i['baseline_value'],$i['calculation_method'],$i['direction']);
        return ['indicator_id'=>$id,'fiscal_year'=>$fy,'reporting_period'=>$period,'state'=>$state,'actual_value'=>$actual,'target_value'=>$target,'reported_at'=>$date,'notes'=>self::text($d,'notes',10000),'status'=>PdoDashboardService::status($achievement,(float)$i['on_track_threshold'],!empty($i['allow_overachievement'])?(float)$i['exceeded_threshold']:null)];
    }
}

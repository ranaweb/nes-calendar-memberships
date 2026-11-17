<?php
if (!defined('ABSPATH')) { exit; }
/** Pure calendar arithmetic. No WordPress writes and no Pro code. */
class NES_Calendar_Recurrence_Engine {
    public static function date($value) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) { return false; }
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
        return $d && $d->format('Y-m-d')===$value;
    }
    private static function ints($value,$min,$max) {
        if ($value==='' || $value===[]) { return []; }
        if (is_string($value)) { $value=preg_split('/\s*,\s*/',trim($value)); }
        foreach ((array)$value as $v) { if (!is_numeric($v) || (float)$v!==(float)(int)$v || (int)$v<$min || (int)$v>$max || (int)$v===0) { throw new InvalidArgumentException('Check the selected days, weekday positions and months. Use whole numbers in the indicated ranges.'); } }
        return array_values(array_unique(array_map('intval',(array)$value)));
    }
    public static function normalize($input) {
        if (!is_array($input)) { throw new InvalidArgumentException('Please check the recurrence settings.'); }
        $start=(string)($input['start'] ?? '');
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})$/D',$start,$m) || !self::date($m[1]) || !self::time($m[2])) { throw new InvalidArgumentException('Choose a valid event start date and time.'); }
        try { $tz=new DateTimeZone((string)($input['timezone'] ?? '')); } catch (Exception $e) { throw new InvalidArgumentException('Choose a valid event timezone.'); }
        $out=['start'=>$start,'timezone'=>$tz->getName(),'duration'=>max(1,min(2678400,(int)($input['duration'] ?? 3600))),'rules'=>[],'additions'=>[],'exclusions'=>[],'exclude_rules'=>[]];
        foreach (['rules','exclude_rules'] as $key) {
            if (count((array)($input[$key] ?? []))>12) { throw new InvalidArgumentException('Use up to 12 schedule rules and 12 exclusion rules.'); }
            foreach ((array)($input[$key] ?? []) as $r) {
                if (!is_array($r)) { throw new InvalidArgumentException('A schedule rule is incomplete.'); }
                $frequency=$r['frequency'] ?? 'weekly';
                if (!in_array($frequency,['daily','weekly','monthly','yearly'],true)) { throw new InvalidArgumentException('Choose daily, weekly, monthly or yearly.'); }
                $rule=['frequency'=>$frequency,'interval'=>max(1,min(99,(int)($r['interval'] ?? 1))),'weekdays'=>self::ints($r['weekdays'] ?? [],1,7),'monthdays'=>self::ints($r['monthdays'] ?? [],-31,31),'months'=>self::ints($r['months'] ?? [],1,12),'ordinal'=>self::ints($r['ordinal'] ?? [],-5,5),'end'=>'never'];
                $rule['monthdays']=array_values(array_filter($rule['monthdays']));
                $rule['ordinal']=array_values(array_filter($rule['ordinal']));
                $rule['inherit_start']=!empty($r['inherit_start']) || empty($r['start']);
                $rule['start']=$rule['inherit_start'] ? substr($start,0,10) : $r['start'];
                if (!self::date($rule['start'])) { throw new InvalidArgumentException('Choose a valid schedule starting date.'); }
                $rule['end']=in_array($r['end'] ?? '',['until','count'],true) ? $r['end'] : 'never';
                if ($rule['end']==='until') {
                    if (!self::date($r['until'] ?? '') || $r['until']<$rule['start']) { throw new InvalidArgumentException('The end date must be on or after the schedule start.'); }
                    $rule['until']=$r['until'];
                }
                if ($rule['end']==='count') { $rule['count']=max(1,min(2000,(int)($r['count'] ?? 1))); }
                $rule['inherit_time']=!empty($r['inherit_time']) || empty($r['time']);
                $rule['time']=$rule['inherit_time'] ? substr($start,11) : $r['time'];
                if (!self::time($rule['time'])) { throw new InvalidArgumentException('Choose a valid schedule start time.'); }
                if (strlen($rule['time'])===5) { $rule['time'].=':00'; }
                $rule['inherit_duration']=!empty($r['inherit_duration']) || empty($r['duration']);
                $rule['duration']=$rule['inherit_duration'] ? $out['duration'] : max(1,min(2678400,(int)$r['duration']));
                $out[$key][]=$rule;
            }
        }
        foreach (array_slice((array)($input['additions'] ?? []),0,366) as $date) {
            if (!is_string($date) || !preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}(?::\d{2})?))?$/D',trim($date),$m) || !self::date($m[1]) || (isset($m[2]) && !self::time($m[2]))) { throw new InvalidArgumentException('Extra dates must be YYYY-MM-DD, optionally followed by HH:MM.'); }
            $time=$m[2] ?? substr($start,11);
            $out['additions'][]=$m[1].' '.$time.(strlen($time)===5?':00':'');
        }
        foreach (array_slice((array)($input['exclusions'] ?? []),0,366) as $range) {
            $parts=is_array($range) ? array_values($range) : preg_split('/\s*\.\.\s*/',trim((string)$range));
            if (!self::date($parts[0] ?? '') || !self::date($parts[1] ?? $parts[0]) || ($parts[1] ?? $parts[0])<$parts[0]) { throw new InvalidArgumentException('Excluded dates use YYYY-MM-DD or YYYY-MM-DD..YYYY-MM-DD.'); }
            $out['exclusions'][]=[$parts[0],$parts[1] ?? $parts[0]];
        }
        return $out;
    }
    public static function time($time) { return is_string($time) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D',$time); }
    private static function matches($day,$anchor,$rule) {
        $days=(int)$anchor->diff($day)->format('%r%a');
        if ($days<0) { return false; }
        $weekday=(int)$day->format('N');
        $weekdays=$rule['weekdays'];
        switch ($rule['frequency']) {
            case 'daily': return $days%$rule['interval']===0 && (!$weekdays || in_array($weekday,$weekdays,true));
            case 'weekly':
                $weeks=intdiv($days+(int)$anchor->format('N')-1,7);
                return $weeks%$rule['interval']===0 && in_array($weekday,$weekdays ?: [(int)$anchor->format('N')],true);
            case 'monthly':
                $months=((int)$day->format('Y')-(int)$anchor->format('Y'))*12+(int)$day->format('n')-(int)$anchor->format('n');
                if ($months%$rule['interval']!==0) { return false; } break;
            case 'yearly':
                if (((int)$day->format('Y')-(int)$anchor->format('Y'))%$rule['interval']!==0 || !in_array((int)$day->format('n'),$rule['months'] ?: [(int)$anchor->format('n')],true)) { return false; } break;
        }
        $n=(int)$day->format('j'); $length=(int)$day->format('t');
        if ($rule['ordinal']) {
            if (!in_array($weekday,$weekdays ?: [(int)$anchor->format('N')],true)) { return false; }
            return in_array(intdiv($n-1,7)+1,$rule['ordinal'],true) || in_array(-(intdiv($length-$n,7)+1),$rule['ordinal'],true);
        }
        $monthdays=$rule['monthdays'] ?: [(int)$anchor->format('j')];
        return (in_array($n,$monthdays,true) || in_array($n-$length-1,$monthdays,true)) && (!$weekdays || in_array($weekday,$weekdays,true));
    }
    /** Number of rule matches preceding a split. The original count is before exceptions. */
    public static function matches_before($rule, $cutoff) {
        $zone = new DateTimeZone('UTC');
        $anchor = new DateTimeImmutable($rule['start'], $zone);
        $last = new DateTimeImmutable(substr($cutoff, 0, 10), $zone);
        $count = 0;
        for ($day = $anchor; $day < $last && $count < $rule['count']; $day = $day->modify('+1 day')) {
            if (self::matches($day, $anchor, $rule)) { ++$count; }
        }
        return $count;
    }
    /** Count endings count matching dates before exclusions; union schedules deduplicate start times. */
    public static function occurrences($input,$from,$through,$limit=2000) {
        $d=self::normalize($input);
        if (!self::date($from) || !self::date($through) || $through<$from) { throw new InvalidArgumentException('Choose a valid preview date range.'); }
        $tz=new DateTimeZone($d['timezone']); $utc=new DateTimeZone('UTC');
        $slots=[]; $excluded=[];
        $add=function($start,$duration)use(&$slots,$tz,$from,$through,$d) {
            if ($start<$d['start'] || substr($start,0,10)<$from || substr($start,0,10)>$through) { return; }
            $local=new DateTimeImmutable($start,$tz);
            // A missing spring-forward local time is skipped, never moved silently.
            if ($local->format('Y-m-d H:i:s')!==$start) { return; }
            $end=$local->modify('+'.$duration.' seconds');
            $slots[$start]=['key'=>$start,'start'=>$local->format('Y-m-d H:i:s'),'end'=>$end->format('Y-m-d H:i:s'),'utc'=>$local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
        };
        $add($d['start'],$d['duration']);
        foreach (['rules','exclude_rules'] as $key) {
            foreach ($d[$key] as $r) {
                $anchor=new DateTimeImmutable($r['start'],$utc);
                $until=new DateTimeImmutable(min($through,$r['until'] ?? $through),$utc);
                if ((int)$anchor->diff($until)->format('%a')>36600) { throw new InvalidArgumentException('Schedule span exceeds 100 years.'); }
                $count=0;
                for ($day=$anchor; $day<=$until; $day=$day->modify('+1 day')) {
                    if (!self::matches($day,$anchor,$r)) { continue; }
                    ++$count;
                    if ($r['end']==='count' && $count>$r['count']) { break; }
                    if ($key==='exclude_rules') { $excluded[$day->format('Y-m-d')]=true; }
                    else { $add($day->format('Y-m-d').' '.$r['time'],$r['duration']); }
                }
            }
        }
        foreach ($d['additions'] as $start) { $add($start,$d['duration']); }
        foreach ($slots as $start=>$slot) {
            $date=substr($start,0,10);
            $skip=isset($excluded[$date]);
            foreach ($d['exclusions'] as $range) { if ($date>=$range[0] && $date<=$range[1]) { $skip=true; break; } }
            if ($skip) { unset($slots[$start]); }
        }
        ksort($slots);
        return array_slice(array_values($slots),0,max(1,min(10000,$limit)));
    }
}

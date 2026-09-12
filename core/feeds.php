<?php
function cms_ics_escape(string $value): string { return str_replace(["\\","\r\n","\n","\r",';',','],["\\\\","\\n","\\n","\\n",'\\;','\\,'],$value); }
function cms_ics_line(string $line): string {
    $out='';
    while (strlen($line)>73) {
        $length=73; while ($length>0 && (ord($line[$length])&0xC0)===0x80) $length--;
        $out.=substr($line,0,$length)."\r\n "; $line=substr($line,$length);
    }
    return $out.$line."\r\n";
}
function cms_ics_export(array $events): string {
    $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//WebCMS//Calendar 2.0//DE','CALSCALE:GREGORIAN'];
    foreach ($events as $event) {
        if (!$event['starts_at']) continue;
        $data=json_decode($event['data_json']??'{}',true)?:[];
        $lines[]='BEGIN:VEVENT'; $lines[]='UID:webcms-'.$event['id'].'@'.(parse_url(site_url(),PHP_URL_HOST)?:'localhost');
        $lines[]='DTSTAMP:'.gmdate('Ymd\THis\Z',strtotime($event['updated_at']));
        $lines[]='DTSTART:'.gmdate('Ymd\THis\Z',strtotime($event['starts_at']));
        if ($event['ends_at']) $lines[]='DTEND:'.gmdate('Ymd\THis\Z',strtotime($event['ends_at']));
        $lines[]='SUMMARY:'.cms_ics_escape($event['title']); $lines[]='DESCRIPTION:'.cms_ics_escape($event['summary']??'');
        $lines[]='LOCATION:'.cms_ics_escape($data['location']??''); $lines[]='URL:'.site_url('/modules.php?entry='.$event['id']); $lines[]='END:VEVENT';
    }
    $lines[]='END:VCALENDAR'; return implode('',array_map('cms_ics_line',$lines));
}
function cms_parse_ics(string $ics): array {
    if (strlen($ics)>2000000 || !str_contains($ics,'BEGIN:VCALENDAR')) throw new RuntimeException('Ungültige oder zu große Kalenderdatei.');
    $ics=preg_replace('/\r?\n[ \t]/','',$ics); $lines=preg_split('/\r?\n/',$ics); $events=[]; $current=null;
    foreach ($lines as $line) {
        $line=rtrim($line,"\r");
        if ($line==='BEGIN:VEVENT') { if ($current!==null) throw new RuntimeException('Verschachtelte Termine werden nicht unterstützt.'); $current=['kind'=>'event','title'=>'','status'=>'draft']; continue; }
        if ($line==='END:VEVENT') { if (!$current || !$current['title'] || empty($current['starts_at'])) throw new RuntimeException('Jeder Termin benötigt Titel und Beginn.'); $events[]=$current; $current=null; if (count($events)>500) throw new RuntimeException('Maximal 500 Termine pro Import.'); continue; }
        if ($current===null || !str_contains($line,':')) continue;
        [$property,$value]=explode(':',$line,2); $parts=explode(';',$property); $key=strtoupper(array_shift($parts));
        if (in_array($key,['RRULE','RDATE','EXDATE','RECURRENCE-ID'],true)) throw new RuntimeException('Serientermine bitte zuerst im Quellkalender in Einzeltermine umwandeln.');
        if ($key==='DTSTART' || $key==='DTEND') {
            $zone=new DateTimeZone(date_default_timezone_get());
            foreach ($parts as $part) if (str_starts_with($part,'TZID=')) { $tz=trim(substr($part,5),'"'); if (!in_array($tz,timezone_identifiers_list(),true)) throw new RuntimeException('Nicht unterstützte Kalender-Zeitzone: '.$tz); $zone=new DateTimeZone($tz); }
            $format=str_ends_with($value,'Z')?'Ymd\THis\Z':(strlen($value)===8?'Ymd':'Ymd\THis');
            if (str_ends_with($value,'Z')) $zone=new DateTimeZone('UTC');
            $date=DateTimeImmutable::createFromFormat('!'.$format,$value,$zone);
            if (!$date || $date->format($format)!==$value) throw new RuntimeException('Ungültiges Datum im Kalender.');
            $current[$key==='DTSTART'?'starts_at':'ends_at']=$date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
        }
        $text=str_replace(['\\n','\\N','\\,','\\;','\\\\'],["\n","\n",',',';','\\'],$value);
        if ($key==='SUMMARY') $current['title']=$text;
        if ($key==='DESCRIPTION') $current['body']=$text;
        if ($key==='LOCATION') $current['location']=$text;
        if ($key==='URL') $current['url']=$text;
    }
    if ($current!==null || !$events || !str_contains($ics,'END:VCALENDAR')) throw new RuntimeException('Kalender ist unvollständig oder enthält keine Termine.');
    return $events;
}

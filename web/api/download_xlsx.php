<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/XlsxWriter.php';

$type = $_GET['type']    ?? 'pml';
$tgl  = $_GET['tanggal'] ?? null;
$db   = getDB();

if (!$tgl) {
    $tgl = $db->query("SELECT MAX(tanggal) FROM progress")->fetchColumn();
}

function hdr($bg='1A2744',$fg='FFFFFF',$sz=10,$align='center'){
    return ['bold'=>true,'bg'=>$bg,'fg'=>$fg,'sz'=>$sz,'align'=>$align,'wrap'=>true,'border'=>true];
}
function dat($bold=false,$fg='000000',$align='left',$bg=null,$numFmt=null){
    $s=['bold'=>$bold,'fg'=>$fg,'align'=>$align,'border'=>true];
    if($bg)     $s['bg']=$bg;
    if($numFmt) $s['numFmt']=$numFmt;
    return $s;
}
function altBg($n){ return $n%2===0?'F7F7F5':null; }
function pctColor($v){ if($v>=50)return '16A34A'; if($v>=25)return 'D97706'; return 'E8560A'; }
function deltaColor($v){ if($v===null)return '9E9E9E'; if($v>0)return '16A34A'; if($v<0)return 'DC2626'; return '6B7280'; }

if ($type==='pml') {

    $stmt=$db->prepare("SELECT p.tanggal,p.email,COALESCE(m.nama,'') AS nama,COALESCE(m.pml,'') AS pml,p.total,p.submitted,p.approved,p.rejected,(p.submitted+p.approved+p.rejected) AS dikerjakan,(p.approved+p.rejected) AS diperiksa FROM progress p LEFT JOIN mapping_nama m ON LOWER(p.email)=LOWER(m.email) WHERE p.tanggal=? AND COALESCE(m.tampil,1)=1 ORDER BY m.pml,m.nama");
    $stmt->execute([$tgl]);
    $rows=$stmt->fetchAll();

    $dates=$db->query("SELECT DISTINCT tanggal FROM progress ORDER BY tanggal DESC")->fetchAll(PDO::FETCH_COLUMN);
    $idx=array_search($tgl,$dates);
    $tglPrev=($idx!==false&&isset($dates[$idx+1]))?$dates[$idx+1]:null;
    $prev=[];
    if($tglPrev){
        $ps=$db->prepare("SELECT email,approved,(approved+rejected) AS diperiksa,(submitted+approved+rejected) AS dik FROM progress WHERE tanggal=?");
        $ps->execute([$tglPrev]);
        foreach($ps->fetchAll() as $r){ $e=strtolower($r['email']); $prev[$e]=['app'=>(int)$r['approved'],'per'=>(int)$r['diperiksa'],'dik'=>(int)$r['dik']]; }
    }

    $wb=new XlsxWriter();
    $si=$wb->addSheet('Rekap PML');
    $wb->setColWidths($si,[20,28,22,8,10,8,7,10,8,7,10,8,7,10]);

    $n14=array_fill(0,14,['v'=>'','s'=>hdr()]);
    $n14[0]['v']="REKAP PROGRESS SE2026 PER PML — $tgl";
    $n14[0]['s']=hdr('1A2744','FFFFFF',12,'center');
    $wb->addRow($si,$n14);

    $hdrs=['PML','Email PCL','Nama PCL','Total','Dikerjakan','Dik%','+Dik','Diperiksa','Per%','+Per','Approved','App%','+App','Submitted'];
    $hRow=[];foreach($hdrs as $h)$hRow[]=['v'=>$h,'s'=>hdr('243158','FFFFFF',10,'center')];
    $wb->addRow($si,$hRow);

    $rn=3;
    foreach($rows as $r){
        $email=strtolower($r['email']);
        $tot=(int)$r['total'];$dik=(int)$r['dikerjakan'];$per=(int)$r['diperiksa'];$app=(int)$r['approved'];$sub=(int)$r['submitted'];
        $dp=$tot>0?round($dik/$tot*100,1):0;$pp=$tot>0?round($per/$tot*100,1):0;$ap=$tot>0?round($app/$tot*100,1):0;
        $sdik=isset($prev[$email])?$dik-$prev[$email]['dik']:null;
        $sper=isset($prev[$email])?$per-$prev[$email]['per']:null;
        $sapp=isset($prev[$email])?$app-$prev[$email]['app']:null;
        $bg=altBg($rn);
        $wb->addRow($si,[
            ['v'=>$r['pml'],'s'=>dat(true,'1A2744','left',$bg)],
            ['v'=>$r['email'],'s'=>dat(false,'6B7280','left',$bg)],
            ['v'=>$r['nama'],'s'=>dat(true,'1A2744','left',$bg)],
            ['v'=>$tot,'s'=>dat(true,'1A2744','right',$bg)],
            ['v'=>$dik,'s'=>dat(false,'1A2744','right',$bg)],
            ['v'=>$dp,'s'=>dat(true,pctColor($dp),'right',$bg,'0.0"%"')],
            ['v'=>$sdik,'s'=>dat(true,deltaColor($sdik),'right',$bg)],
            ['v'=>$per,'s'=>dat(false,'1A2744','right',$bg)],
            ['v'=>$pp,'s'=>dat(true,pctColor($pp),'right',$bg,'0.0"%"')],
            ['v'=>$sper,'s'=>dat(true,deltaColor($sper),'right',$bg)],
            ['v'=>$app,'s'=>dat(false,'16A34A','right',$bg)],
            ['v'=>$ap,'s'=>dat(true,pctColor($ap),'right',$bg,'0.0"%"')],
            ['v'=>$sapp,'s'=>dat(true,deltaColor($sapp),'right',$bg)],
            ['v'=>$sub,'s'=>dat(false,'D97706','right',$bg)],
        ]);
        $rn++;
    }
    $fname="SELARAS_PML_$tgl.xlsx";

} elseif ($type==='lk') {

    // Cek apakah tabel assignment sudah ada data
    $asnCount = $db->query("SELECT COUNT(*) FROM assignment")->fetchColumn();

    $namaKec=['5371010'=>'ALAK','5371020'=>'MAULAFA',
              '5371030'=>'OEBOBO','5371031'=>'KOTA RAJA',
              '5371040'=>'KELAPA LIMA','5371041'=>'KOTA LAMA'];

    $wb=new XlsxWriter();
    $si=$wb->addSheet('LK Beban Kerja');
    $wb->setColWidths($si,[4,20,22,28,22,10,12,6,8,8,6,8,8,6,8,8,10]);

    // Header dokumen
    $n17=array_fill(0,17,['v'=>'','s'=>hdr('1A2744','FFFFFF',12,'center')]);
    $n17[0]['v']='LEMBAR KERJA BEBAN KERJA PETUGAS LAPANGAN SE2026';
    $wb->addRow($si,$n17);
    $s17=array_fill(0,17,['v'=>'','s'=>hdr('243158','FFFFFF',9,'center')]);
    $s17[0]['v']="Kota Payakumbuh — Sumatera Barat | Tanggal Progress: $tgl | Data Assignment: ".($asnCount>0?'Tersedia':'Belum diambil');
    $wb->addRow($si,$s17);

    // Header grup
    $grpStyle = hdr('1A2744','FFFFFF',9,'center');
    $grpOrange = hdr('E37F2A','FFFFFF',9,'center');
    $grpGreen  = hdr('16A34A','FFFFFF',9,'center');
    $wb->addRow($si,[
        ['v'=>'No','s'=>$grpStyle],
        ['v'=>'Nama PML','s'=>$grpStyle],
        ['v'=>'Nama PCL','s'=>$grpStyle],
        ['v'=>'Email PCL','s'=>$grpStyle],
        ['v'=>'Kecamatan','s'=>$grpStyle],
        ['v'=>'Kode Kec','s'=>$grpStyle],
        ['v'=>'Kode Desa','s'=>$grpStyle],
        ['v'=>'Jml SLS','s'=>$grpStyle],
        ['v'=>'Target Ruta','s'=>$grpOrange],
        ['v'=>'Target Usaha','s'=>$grpOrange],
        ['v'=>'Total Target','s'=>$grpOrange],
        ['v'=>'Selesai Ruta','s'=>$grpGreen],
        ['v'=>'Selesai Usaha','s'=>$grpGreen],
        ['v'=>'% Ruta','s'=>$grpGreen],
        ['v'=>'% Usaha','s'=>$grpGreen],
        ['v'=>'Approved','s'=>$grpGreen],
        ['v'=>'% App','s'=>$grpGreen],
    ]);

    if ($asnCount > 0) {
        // Ada data assignment — ambil per email per desa dengan pemisahan Ruta/Usaha
        $asnStmt = $db->prepare("
            SELECT
                a.email,
                a.kode_kec,
                a.kode_desa,
                COUNT(DISTINCT a.kode_sls)                                          AS jml_sls,
                SUM(CASE WHEN a.data6='KELUARGA' THEN 1 ELSE 0 END)               AS target_ruta,
                SUM(CASE WHEN a.data6!='' AND a.data6!='KELUARGA' THEN 1 ELSE 0 END) AS target_usaha,
                COUNT(*)                                                             AS total_target,
                -- Selesai = status APPROVED atau SUBMITTED (sudah dicacah)
                SUM(CASE WHEN a.data6='KELUARGA'
                          AND a.status IN ('SUBMITTED BY PENCACAH','APPROVED BY PENGAWAS','REJECTED BY PENGAWAS')
                         THEN 1 ELSE 0 END)                                        AS selesai_ruta,
                SUM(CASE WHEN a.data6!='' AND a.data6!='KELUARGA'
                          AND a.status IN ('SUBMITTED BY PENCACAH','APPROVED BY PENGAWAS','REJECTED BY PENGAWAS')
                         THEN 1 ELSE 0 END)                                        AS selesai_usaha,
                SUM(CASE WHEN a.status='APPROVED BY PENGAWAS' THEN 1 ELSE 0 END)  AS approved,
                COALESCE(m.nama, a.email)                                           AS nama,
                COALESCE(m.pml, '')                                                 AS pml
            FROM assignment a
            LEFT JOIN mapping_nama m ON LOWER(a.email)=LOWER(m.email)
            WHERE COALESCE(m.tampil,1)=1
              AND a.data6 != ''
            GROUP BY a.email, a.kode_kec, a.kode_desa
            ORDER BY m.pml, m.nama, a.kode_kec, a.kode_desa
        ");
        $asnStmt->execute();
        $asnRows = $asnStmt->fetchAll();

        $no=1; $rn=4;
        foreach($asnRows as $r){
            $bg=altBg($rn);
            $kk=$r['kode_kec']; $kd=$r['kode_desa'];
            $tRuta=(int)$r['target_ruta']; $tUsaha=(int)$r['target_usaha']; $tot=(int)$r['total_target'];
            $sRuta=(int)$r['selesai_ruta']; $sUsaha=(int)$r['selesai_usaha']; $app=(int)$r['approved'];
            $pRuta  = $tRuta>0  ? round($sRuta/$tRuta*100,1)   : 0;
            $pUsaha = $tUsaha>0 ? round($sUsaha/$tUsaha*100,1) : 0;
            $pApp   = $tot>0    ? round($app/$tot*100,1)        : 0;

            $wb->addRow($si,[
                ['v'=>$no,                                    's'=>dat(false,'6B7280','center',$bg)],
                ['v'=>$r['pml'],                              's'=>dat(true,'1A2744','left',$bg)],
                ['v'=>$r['nama'],                             's'=>dat(true,'1A2744','left',$bg)],
                ['v'=>$r['email'],                            's'=>dat(false,'6B7280','left',$bg)],
                ['v'=>isset($namaKec[$kk])?$namaKec[$kk]:$kk,'s'=>dat(false,'1A2744','left',$bg)],
                ['v'=>$kk,                                    's'=>dat(false,'6B7280','center',$bg)],
                ['v'=>$kd,                                    's'=>dat(false,'6B7280','center',$bg)],
                ['v'=>(int)$r['jml_sls'],                    's'=>dat(false,'1A2744','center',$bg)],
                ['v'=>$tRuta,                                 's'=>dat(false,'E37F2A','right',$bg)],
                ['v'=>$tUsaha,                                's'=>dat(false,'E37F2A','right',$bg)],
                ['v'=>$tot,                                   's'=>dat(true,'1A2744','right',$bg)],
                ['v'=>$sRuta,                                 's'=>dat(false,'16A34A','right',$bg)],
                ['v'=>$sUsaha,                                's'=>dat(false,'16A34A','right',$bg)],
                ['v'=>$pRuta,                                 's'=>dat(true,pctColor($pRuta),'right',$bg,'0.0"%"')],
                ['v'=>$pUsaha,                                's'=>dat(true,pctColor($pUsaha),'right',$bg,'0.0"%"')],
                ['v'=>$app,                                   's'=>dat(false,'16A34A','right',$bg)],
                ['v'=>$pApp,                                  's'=>dat(true,pctColor($pApp),'right',$bg,'0.0"%"')],
            ]);
            $no++; $rn++;
        }

        // Catatan
        $wb->addRow($si,[['v'=>'Sumber: tabel assignment SELARAS | data6=KELUARGA → Ruta, lainnya → Usaha | Selesai = status SUBMITTED+APPROVED+REJECTED','s'=>['fg'=>'9E9E9E','sz'=>9,'align'=>'left','border'=>true]]]);

    } else {
        // Belum ada data assignment — fallback ke progress_wilayah
        $stmt=$db->prepare("SELECT p.email,COALESCE(m.nama,p.email) AS nama,COALESCE(m.pml,'') AS pml,p.total,(p.submitted+p.approved+p.rejected) AS dikerjakan,(p.approved+p.rejected) AS diperiksa,p.approved FROM progress p LEFT JOIN mapping_nama m ON LOWER(p.email)=LOWER(m.email) WHERE p.tanggal=? AND COALESCE(m.tampil,1)=1 ORDER BY m.pml,m.nama");
        $stmt->execute([$tgl]);
        $progRows=$stmt->fetchAll();

        $wilStmt=$db->prepare("SELECT email,SUBSTR(region_code,1,7) AS kode_kec,SUBSTR(region_code,1,10) AS kode_desa,SUM(total) AS total_wil FROM progress_wilayah WHERE tanggal=? GROUP BY email,kode_kec,kode_desa ORDER BY email,kode_kec,kode_desa");
        $wilStmt->execute([$tgl]);
        $wilMap=[];
        foreach($wilStmt->fetchAll() as $w) $wilMap[strtolower($w['email'])][]=$w;

        $no=1; $rn=4;
        foreach($progRows as $r){
            $email=strtolower($r['email']);
            $tot=(int)$r['total'];$dik=(int)$r['dikerjakan'];$per=(int)$r['diperiksa'];$app=(int)$r['approved'];
            $dp=$tot>0?round($dik/$tot*100,1):0;$pp=$tot>0?round($per/$tot*100,1):0;$ap=$tot>0?round($app/$tot*100,1):0;
            $wils=$wilMap[$email]??[null];
            foreach($wils as $w){
                $bg=altBg($rn);
                $kk=$w?$w['kode_kec']:''; $kd=$w?$w['kode_desa']:''; $tw=$w?(int)$w['total_wil']:$tot;
                $wb->addRow($si,[
                    ['v'=>$no,'s'=>dat(false,'6B7280','center',$bg)],
                    ['v'=>$r['pml'],'s'=>dat(true,'1A2744','left',$bg)],
                    ['v'=>$r['nama'],'s'=>dat(true,'1A2744','left',$bg)],
                    ['v'=>$r['email'],'s'=>dat(false,'6B7280','left',$bg)],
                    ['v'=>isset($namaKec[$kk])?$namaKec[$kk]:$kk,'s'=>dat(false,'1A2744','left',$bg)],
                    ['v'=>$kk,'s'=>dat(false,'6B7280','center',$bg)],
                    ['v'=>$kd,'s'=>dat(false,'6B7280','center',$bg)],
                    ['v'=>'','s'=>dat(false,'9E9E9E','center',$bg)],
                    ['v'=>'?','s'=>dat(false,'9E9E9E','right',$bg)], // Ruta belum ada
                    ['v'=>'?','s'=>dat(false,'9E9E9E','right',$bg)], // Usaha belum ada
                    ['v'=>$tw,'s'=>dat(true,'1A2744','right',$bg)],
                    ['v'=>'','s'=>dat(false,'9E9E9E','right',$bg)],
                    ['v'=>'','s'=>dat(false,'9E9E9E','right',$bg)],
                    ['v'=>$dp,'s'=>dat(true,pctColor($dp),'right',$bg,'0.0"%"')],
                    ['v'=>'','s'=>dat(false,'9E9E9E','right',$bg)],
                    ['v'=>$app,'s'=>dat(false,'16A34A','right',$bg)],
                    ['v'=>$ap,'s'=>dat(true,pctColor($ap),'right',$bg,'0.0"%"')],
                ]);
                $no++;$rn++;
            }
        }
        $wb->addRow($si,[['v'=>'⚠️ Data assignment belum diambil — jalankan SIHARAU Detail untuk data Ruta/Usaha lengkap','s'=>['fg'=>'DC2626','sz'=>9,'align'=>'left','border'=>true,'bold'=>true]]]);
    }

    $fname="LK_Beban_Kerja_SE2026_$tgl.xlsx";

} else { die('Unknown type'); }

$tmp=tempnam(sys_get_temp_dir(),'selaras_').'.xlsx';
$wb->save($tmp);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Content-Length: '.filesize($tmp));
header('Cache-Control: max-age=0');
readfile($tmp);
@unlink($tmp);
exit;
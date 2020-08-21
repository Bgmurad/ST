<?php
require("snmplib.class.php");
function get_user_data()
{
	global $db, $vars;
	
	$r = $db->fetch_row_array("
SELECT a.is_blocked,  round(floor(a.balance*100)/100,2) balance, a.credit, u.full_name, u.login, u.is_juridical, u.work_telephone, u.home_telephone, u.mobile_telephone, u.password
, u.actual_address, u.building, u.flat_number,u.email, u.passport, u.comments, a.int_status
 FROM accounts AS a, users_accounts AS ua, users AS u
 WHERE a.external_id=".$vars['external_id']['val']." AND a.is_deleted=0
  AND ua.account_id=a.id AND ua.is_deleted=0
  AND u.id=ua.uid AND u.is_deleted=0
");
	if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];

	var_set('full_name', $r['full_name']);
	var_set('login', $r['login']);
	var_set('password', $r['password']);
	var_set('balance', $r['balance']);
	var_set('credit', $r['credit']);
	var_set('is_blocked', $r['is_blocked']);
	var_set('is_juridical', $r['is_juridical']);
	var_set('work_telephone', $r['work_telephone']);
	var_set('home_telephone', $r['home_telephone']);
	var_set('mobile_telephone', $r['mobile_telephone']);
	var_set('actual_address', $r['actual_address']);
	var_set('building', $r['building']);
	var_set('flat_number', $r['flat_number']);
	var_set('email', $r['email']);
	var_set('comment', $r['comments']);
	var_set('int_status', $r['int_status']);
	var_set('passport', $r['passport']);
	return true;
}

function get_contracts()
{
	global $db, $vars;
    $firm = $vars['firm']['val'];
    $house_id = $vars['house_id']['val'];
    $flat = $vars['flat_number']['val'];
	
	$q = $db->query("
select 
    a.external_id account
    , u.full_name
    , a.is_blocked
    , max( bi.start_date ) last_block 
from 
    users u 
    join accounts a on a.id = u.basic_account and a.is_deleted = 0 
    join user_additional_params uap on uap.userid = u.id and uap.paramid = 2 and uap.value = '$firm'
    left join blocks_info bi on bi.account_id = a.id 
where 
    u.is_deleted = 0 
    and u.house_id = $house_id 
    and u.flat_number = $flat 
group by u.id
");
    $report = array();
    while( $r = $db->fetch_array( $q ) )
    {
        $report[] = $r;
    }

	var_set( 'report', $report );
	return true;
}

function get_balance()
{
	global $db, $vars;

    $sql_firm = '';
    if( $vars['firm']['val'] )
        $sql_firm = 'join users u on u.basic_account = a.id and u.is_deleted = 0 
        join user_additional_params uap on uap.userid = u.id and uap.paramid = 2 and uap.value = "' . $vars['firm']['val'] . '"';
	
//	$r = $db->fetch_row_array("SELECT truncate(balance,2), truncate(credit,2) FROM accounts
//  WHERE external_id=".$vars['external_id']['val']." AND is_deleted=0");
$r = $db->fetch_row_array("SELECT a.balance balance, a.credit credit,2 FROM accounts a $sql_firm WHERE a.external_id=".$vars['external_id']['val']." AND a.is_deleted=0");
	if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];

	var_set('balance', $r['balance']);
	var_set('credit', $r['credit']);
	return true;
}

#function get_user_data_ids($external_id)
#{
#global $db, $vars;
#
#$r = $db->fetch_row_array("
#SELECT u.id AS uid, a.id AS account_id, u.basic_account
#FROM accounts AS a, users_accounts AS ua, users AS u
#WHERE a.external_id=".$external_id." AND a.is_deleted=0
#AND ua.account_id=a.id AND ua.is_deleted=0
#AND u.id=ua.uid AND u.is_deleted=0");
#
#if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];
#
#        var_set('user_id', $r['uid']);
#        var_set('account_id', $r['account_id']);
#        return true;

#}

function get_status()
{
	global $db, $vars;
	
	$r = $db->fetch_row_array("SELECT is_blocked FROM accounts WHERE external_id=".$vars['external_id']['val']." AND is_deleted=0");
	if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];

	var_set('is_blocked', $r['is_blocked']);
	return true;
}

function get_tariffs()
{
	global $db, $vars, $ret;
	
	$sql = "
SELECT t.id, t.name, t.comments, lt.credit, lt.periodic_type
FROM tariffs AS t, lru_tarif AS lt, lru_firms AS lf, lru_tarif_group AS ltg
WHERE t.is_deleted=0 AND lt.tarif_id=t.id AND lt.type='действующий' AND lt.is_juridical=".$vars['is_juridical']['val']."
AND lf.name='".$vars['firm']['val']."' AND lf.is_deleted=0 AND lt.firm_id=lf.id 
AND ltg.name='".$vars['tariff_group']['val']."' AND lt.tarif_group_id=ltg.id
AND t.id IN (SELECT tarif_id FROM tt.tlc_geo_groups_tariffs WHERE group_id IN (SELECT group_id FROM tt.tlc_geo_groups_content 
WHERE geo_id=".$vars['geo_id']['val'].") or group_id=29 )
";
//echo $sql;
	$q = $db->query($sql);
	if(!$q) return $db->error;
	$t = array();
	while($r = $db->fetch_array($q))
	{
		$s = array();
		$sql = "
SELECT sd.id, sd.service_name, sd.link_by_default, sd.service_type, psd.cost, sd.parent_service_id
FROM tariffs_services_link AS tsl, services_data AS sd
LEFT JOIN periodic_services_data AS psd ON psd.id=sd.id
WHERE tsl.is_deleted=0 AND tsl.tariff_id=".$r['id']." AND sd.id=tsl.service_id AND sd.is_deleted=0";
		$qq = $db->query($sql);
		while($rr = $db->fetch_array($qq))
		{
			switch($rr['service_type'])
			{
				case 1:
					$rr['type'] = "Разовая услуга";
					$tt = $db->fetch_row_array("SELECT cost FROM once_service_data WHERE id=".$rr['id']);
					$rr['cost'] = $tt['cost'];
					break;
				case 6:
					$rr['type'] = "Телефония";
					break;
				case 2:
				        $rr['type'] = "Периодическая услуга";
					break;
				                                                                                					
				case 3:
                                        $rr['type'] = "Интернет";
                                        break;	
				default:
					$rr['type'] = "Неизвестный тип услуги";
			}
			$s[] = array(
				"id" => $rr['id'],
				"name" => $rr['service_name'],
				"default" => $rr['link_by_default'],
				"type" => $rr['type'],
				"cost" => $rr['cost'],
				"parent_service_id" => $rr['parent_service_id']
				
				);
		}

		$t[] = array(
			"id" => $r['id'],
			"name" => $r['name'],
			"credit" => $r['credit'],
			"periodic" => $r['periodic_type'],
			"services" => $s);
	}
	var_set("report", $t);
	return true;
}

function get_recomended_tariffs()
{
global $db, $vars, $ret;



$sql1="

create temporary table pre_tariffs_".$vars['tariff_id']['val']."

SELECT t.id,  sum(IFNULL(psd.cost,0)+ IFNULL(osd.cost,0)) AS sum_cost, lu.boi1 AS speed
FROM tariffs_services_link AS tsl
LEFT JOIN services_data AS sd on sd.id=tsl.service_id
LEFT JOIN periodic_services_data AS psd ON psd.id=sd.id
LEFT JOIN once_service_data AS osd ON osd.id=sd.id
LEFT JOIN tariffs AS t on tsl.tariff_id=t.id
LEFT JOIN lru_tarif AS lt on lt.tarif_id=t.id
LEFT JOIN lru_unlim1 AS lu on lu.tarif_id=t.id

WHERE tsl.is_deleted=0 AND tsl.tariff_id=t.id AND  sd.is_deleted=0

and lt.periodic_type='month'
and sd.link_by_default=1
and lt.type='действующий'
and is_juridical=0
and lt.firm_id=(select lt.firm_id from lru_tarif AS lt where tarif_id='".$vars['tariff_id']['val']."')
and lt.tarif_group_id=(select lt.tarif_group_id from lru_tarif AS lt where tarif_id='".$vars['tariff_id']['val']."')

and t.id in 
(SELECT tarif_id FROM tt.tlc_geo_groups_tariffs WHERE group_id IN (SELECT group_id FROM tt.tlc_geo_groups_content
WHERE geo_id in
(
select geo_id FROM tt.tlc_geo_groups_content where group_id in 
(
select group_id  FROM tt.tlc_geo_groups_tariffs where tarif_id='".$vars['tariff_id']['val']."'
)
)
)
)
group by t.id having

sum_cost>
(
SELECT sum(IFNULL(psd.cost,0)+ IFNULL(osd.cost,0)) AS cost
FROM tariffs_services_link AS tsl
LEFT JOIN services_data AS sd on sd.id=tsl.service_id
LEFT JOIN periodic_services_data AS psd ON psd.id=sd.id
LEFT JOIN once_service_data AS osd ON osd.id=sd.id
WHERE tsl.is_deleted=0 AND  sd.is_deleted=0
and tsl.tariff_id='".$vars['tariff_id']['val']."'
and sd.link_by_default=1
group by tsl.tariff_id
)

and

 speed>= (select lu.boi1 AS speed from lru_unlim1 AS lu where lu.tarif_id='".$vars['tariff_id']['val']."')

";

$q1 = $db->query($sql1);


$sql = "

SELECT t.id, t.name, t.comments, lt.credit, lt.periodic_type
FROM tariffs AS t, lru_tarif AS lt, pre_tariffs_".$vars['tariff_id']['val']." pt
Where t.id=lt.tarif_id and t.id=pt.id
";
//echo $sql;
$q = $db->query($sql);
if(!$q) return $db->error;
$t = array();
while($r = $db->fetch_array($q))
{
$s = array();
$sql = "
SELECT sd.id, sd.service_name, sd.link_by_default, sd.service_type, psd.cost
FROM tariffs_services_link AS tsl, services_data AS sd
LEFT JOIN periodic_services_data AS psd ON psd.id=sd.id
WHERE tsl.is_deleted=0 AND tsl.tariff_id=".$r['id']." AND sd.id=tsl.service_id AND sd.is_deleted=0";
$qq = $db->query($sql);
while($rr = $db->fetch_array($qq))
{
switch($rr['service_type'])
{
case 1:
$rr['type'] = "Разовая услуга";
$tt = $db->fetch_row_array("SELECT cost FROM once_service_data WHERE id=".$rr['id']);
$rr['cost'] = $tt['cost'];
break;
case 6:
$rr['type'] = "Телефония";
break;
case 2:
$rr['type'] = "Периодическая услуга";
break;

case 3:
$rr['type'] = "Интернет";
break;
default:
$rr['type'] = "Неизвестный тип услуги";
}
$s[] = array(
"id" => $rr['id'],
"name" => $rr['service_name'],
"default" => $rr['link_by_default'],
"type" => $rr['type'],
"cost" => $rr['cost']);
}

$t[] = array(
"id" => $r['id'],
"name" => $r['name'],
"credit" => $r['credit'],
"periodic" => $r['periodic_type'],
"services" => $s);
}
var_set("report", $t);

$sql2="drop table pre_tariffs_".$vars['tariff_id']['val']."";
$q2 = $db->query($sql2);
return true;
}



function get_available_tariffs()
{
global $db, $vars, $ret;



$sql1="

create temporary table pre_tariffs_".$vars['tariff_id']['val']."

SELECT t.id,  sum(IFNULL(psd.cost,0)+ IFNULL(osd.cost,0)) AS sum_cost, lu.boi1 AS speed
FROM tariffs_services_link AS tsl
LEFT JOIN services_data AS sd on sd.id=tsl.service_id
LEFT JOIN periodic_services_data AS psd ON psd.id=sd.id
LEFT JOIN once_service_data AS osd ON osd.id=sd.id
LEFT JOIN tariffs AS t on tsl.tariff_id=t.id
LEFT JOIN lru_tarif AS lt on lt.tarif_id=t.id
LEFT JOIN lru_unlim1 AS lu on lu.tarif_id=t.id

WHERE tsl.is_deleted=0 AND tsl.tariff_id=t.id AND  sd.is_deleted=0

#and (lt.periodic_type='month' or lt.periodic_type='day_traffic')
and t.id!='".$vars['tariff_id']['val']."'
and lt.periodic_type='month'
and sd.link_by_default=1
and lt.type='действующий'
and is_juridical=0
and lt.firm_id=(select lt.firm_id from lru_tarif AS lt where tarif_id='".$vars['tariff_id']['val']."')
and lt.tarif_group_id=(select lt.tarif_group_id from lru_tarif AS lt where tarif_id='".$vars['tariff_id']['val']."')

and t.id in
(SELECT tarif_id FROM tt.tlc_geo_groups_tariffs WHERE group_id IN (SELECT group_id FROM tt.tlc_geo_groups_content
WHERE geo_id in
(
select geo_id FROM tt.tlc_geo_groups_content where group_id in
(
select group_id  FROM tt.tlc_geo_groups_tariffs where tarif_id='".$vars['tariff_id']['val']."'
)
)
)
)
group by t.id";

$q1 = $db->query($sql1);


$sql = "

SELECT t.id, t.name, t.comments, lt.credit, 
lt.periodic_type
#'month' as periodic_type
FROM tariffs AS t, lru_tarif AS lt, pre_tariffs_".$vars['tariff_id']['val']." pt
Where t.id=lt.tarif_id and t.id=pt.id
";
//echo $sql;
$q = $db->query($sql);
if(!$q) return $db->error;
$t = array();
while($r = $db->fetch_array($q))
{
$s = array();
$sql = "
SELECT sd.id, sd.service_name, sd.link_by_default, sd.service_type, psd.cost
FROM tariffs_services_link AS tsl, services_data AS sd
LEFT JOIN periodic_services_data AS psd ON psd.id=sd.id
WHERE tsl.is_deleted=0 AND tsl.tariff_id=".$r['id']." AND sd.id=tsl.service_id AND sd.is_deleted=0";
$qq = $db->query($sql);
while($rr = $db->fetch_array($qq))
{
switch($rr['service_type'])
{
case 1:
$rr['type'] = "Разовая услуга";
$tt = $db->fetch_row_array("SELECT cost FROM once_service_data WHERE id=".$rr['id']);
$rr['cost'] = $tt['cost'];
break;
case 6:
$rr['type'] = "Телефония";
break;
case 2:
$rr['type'] = "Периодическая услуга";
break;

case 3:
$rr['type'] = "Интернет";
break;
default:
$rr['type'] = "Неизвестный тип услуги";
}
$s[] = array(
"id" => $rr['id'],
"name" => $rr['service_name'],
"default" => $rr['link_by_default'],
"type" => $rr['type'],
"cost" => $rr['cost']);
}

$t[] = array(
"id" => $r['id'],
"name" => $r['name'],
"credit" => $r['credit'],
"periodic" => $r['periodic_type'],
"services" => $s);
}
var_set("report", $t);

$sql2="drop table pre_tariffs_".$vars['tariff_id']['val']."";
$q2 = $db->query($sql2);
return true;
}




function get_tariffs_oper()
{
	global $db, $vars, $ret;
    $tariff_group = $vars['tariff_group']['val'];
	$tariff_group = $tariff_group == 'DUMMY' || $tariff_group == 'Основная' || $tariff_group == 'Автоматические' ? "'Основная', 'Автоматические'" : "'$tariff_group'";

	$sql = "
SELECT t.id, t.name, t.comments, lt.credit, lt.periodic_type, lt.type
FROM tariffs AS t, lru_tarif AS lt, lru_firms AS lf, lru_tarif_group AS ltg
WHERE t.is_deleted=0 AND lt.tarif_id=t.id AND ( lt.type='действующий' || lt.type='акция' || lt.type='новые абоненты' || lt.type='системный' ) AND lt.is_juridical=".$vars['is_juridical']['val']."
AND lf.name='".$vars['firm']['val']."' AND lf.is_deleted=0 AND lt.firm_id=lf.id 
AND ltg.name in ( $tariff_group ) AND lt.tarif_group_id=ltg.id
order by t.name
";
//echo $sql;
	$q = $db->query($sql);
	if(!$q) return $db->error;
	$t = array();
	while($r = $db->fetch_array($q))
	{
		$s = array();
		$sql = "
SELECT sd.id, sd.service_name, sd.link_by_default, sd.service_type, psd.cost
FROM tariffs_services_link AS tsl, services_data AS sd
LEFT JOIN periodic_services_data AS psd ON psd.id=sd.id
WHERE tsl.is_deleted=0 AND tsl.tariff_id=".$r['id']." AND sd.id=tsl.service_id AND sd.is_deleted=0";
		$qq = $db->query($sql);
		while($rr = $db->fetch_array($qq))
		{
			switch($rr['service_type'])
			{
				case 1:
					$rr['type'] = "Разовая услуга";
					$tt = $db->fetch_row_array("SELECT cost FROM once_service_data WHERE id=".$rr['id']);
					$rr['cost'] = $tt['cost'];
					break;
				case 6:
					$rr['type'] = "Телефония";
					break;
				case 2:
				        $rr['type'] = "Периодическая услуга";
					break;
				                                                                                					
				case 3:
                                        $rr['type'] = "Интернет";
                                        break;	
				default:
					$rr['type'] = "Неизвестный тип услуги";
			}
			$s[] = array(
				"id" => $rr['id'],
				"name" => $rr['service_name'],
				"default" => $rr['link_by_default'],
				"type" => $rr['type'],
				"cost" => $rr['cost']);
		}

		$t[] = array(
			"id" => $r['id'],
			"name" => $r['name'],
			"credit" => $r['credit'],
			"periodic" => $r['periodic_type'],
			"type" => $r['type'],
			"services" => $s);
	}
	var_set("report", $t);
	return true;
}




//DrWeb

function get_drweb_for_user()
{
global $db, $vars;

// $s= array();
//старый запрос
$sql1 = "
select add_date,expire_date,link, 
CASE rate when '91644cc3-1dc1-42dc-a41e-5ea001f5538d' then 'DrWeb Премиум' 
when 'ebe76ffc-69e1-4757-b2b3-41506832bc9b' then 'DrWeb Стандарт' 
when '2888b7ff-3625-465e-bcb8-957de17f6458' then 'DrWeb Классик' 
when '01fe9e60-6570-11de-b827-0002a5d5c51b' then 'DrWeb Премиум Сервер'
END as service_name from  drweb_data
where external_id=".$vars['external_id']['val']."
and(expire_date>unix_timestamp(now()) or expire_date=0)

";
//новый запрос
$sql="
select dd.add_date,dd.expire_date,link,
sd.service_name from  

drweb_data dd, services_data sd

where dd.service_id=sd.id
#and is_deleted=0
and external_id=".$vars['external_id']['val']."
and(expire_date>unix_timestamp(now()) or expire_date=0)
";


//echo $sql;
$q = $db->query($sql);
while($r = $db-> fetch_array($q))
{
// echo $r;
$s[] = array(
"add_date" => $r['add_date'],
"expire_date" => $r['expire_date'],
"link" => $r['link'],
"service_name" => $r['service_name']
);
}
var_set('report', $s);
return true;

}



function get_services()
{
    global $db, $vars;

    $firm = $vars['firm']['val'];
    $oper = "";
    if( $vars['is_oper']['val'] )
        $oper = "or type='акция'";

    $is_juridical = $vars['is_juridical']['val'] ? 1 : 0;

    $sql = "
select lf.name, sd.id, sd.comment, sd.service_name, CASE sd.service_type when 1 then 'Разовая услуга' when 2 then 'Переодическая услуга'
when 3 then 'Интернет'	END as service_type,
(ifnull(osd.cost,0))+(ifnull(psd.cost,0)) as cost
from services_data sd
    
left join once_service_data osd on sd.id=osd.id
left join periodic_services_data psd on psd.id=sd.id
left join lru_service AS ls on (ls.service_id=sd.id  and ( type='действующий' $oper ) and ls.is_juridical = $is_juridical )
left join lru_firms AS lf on lf.id=ls.firm_id and ( lf.name = '$firm' or sd.id in ( 774, 775, 776, 777 ) )
where sd.tariff_id=0 and lf.name != 'NULL'
and sd.is_deleted=0
order by sd.service_name;
        ";
        
    //echo $sql;
    $q = $db->query($sql);
    while($r = $db-> fetch_array($q))
    {
        // echo $r;
        $s[] = array(
        "id" => $r['id'],
        "service_name" => $r['service_name'],
        "unique" => $r['comment'],
        "service_type" => $r['service_type'],
        "cost" => $r['cost']
        );
    }
    var_set('report', $s);
    return true;

}
                    


function get_user_tariffs()
    {
    global $db, $vars, $ret;
    global $MA_url, $MA_key, $MA_net_id;

#       $sql = "

#   SELECT atl.id as tlink_id, t.id,  t.name, t.comments
#   FROM tariffs t, account_tariff_link atl, accounts a
#   ,lru_tarif AS lt, lru_firms AS lf, lru_tarif_group AS ltg

#   where t.id=atl.tarifF_id and atl.is_deleted=0 and atl.account_id =a.id
#   and a.external_id=".$vars['external_id']['val']." AND a.is_deleted=0

#   and   t.is_deleted=0 AND lt.tarif_id=t.id AND lt.type='действующий' 
#   AND lf.name='".$vars['firm']['val']."' AND lf.is_deleted=0 AND lt.firm_id=lf.id
#   AND ltg.name='".$vars['tariff_group']['val']."' AND lt.tarif_group_id=ltg.id 
#       
#       
#       ";

    $sql = "
SELECT atl.id as tlink_id, t.id,  t.name, t.comments, atl.next_tariff_id next_tariff_id
FROM 
    tariffs t 
    join account_tariff_link atl on atl.tarifF_id = t.id
    join accounts a on a.id = atl.account_id
    join lru_tarif AS lt on lt.tarif_id = t.id
    join lru_tarif_group AS ltg on ltg.id = lt.tarif_group_id
    left join lru_firms AS lf on lf.id = lt.firm_id

where  
    atl.is_deleted=0 
    AND a.is_deleted=0
    and t.is_deleted=0 
    and a.external_id=".$vars['external_id']['val']." 
    " . ( $vars['tariff_group']['val'] ? ( "AND ltg.name='".$vars['tariff_group']['val']."'" ) : "" ) . "
    " . ( $vars['firm']['val'] ? ( "AND lf.is_deleted=0 AND lf.name='" . $vars['firm']['val'] . "'" ) : "" );

//echo $sql;
    $q = $db->query($sql);
    if(!$q) return $db->error;
    $t = array();
    while($r = $db->fetch_array($q))
	{
	$s = array();
#   $sql = "


#   SELECT sl.id as slink_id, sd.id, sd.service_name, sd.link_by_default, sd.service_type, psd.cost, psl.need_del, psl.expire_date  
#   FROM tariffs_services_link AS tsl, services_data AS sd, periodic_services_data AS psd ,service_links sl, periodic_service_links psl
#   WHERE  psd.id=sd.id and tsl.is_deleted=0 AND tsl.tariff_id=".$r['id']." AND sd.id=tsl.service_id AND sd.is_deleted=0 and sl.id=psl.id
#   and sl.is_deleted=0 and sl.service_id=sd.id  and sl.account_id in (SELECT a.id from accounts a 
#   WHERE a.external_id=".$vars['external_id']['val']." AND a.is_deleted=0)
#   ";
    $sql = "

SELECT sl.id as slink_id, sd.id, sd.service_name, sd.link_by_default, sd.service_type, psd.cost, psl.need_del, psl.expire_date, sd.parent_service_id 
    FROM services_data AS sd
        join periodic_services_data AS psd on psd.id = sd.id
        join service_links sl on sl.service_id = sd.id
        join periodic_service_links psl on psl.id = sl.id
        
    WHERE  
    sl.tariff_link_id=".$r['tlink_id']." 
    AND
     sd.is_deleted=0
    and sl.is_deleted=0 
";


	$qq = $db->query($sql);
	while($rr = $db->fetch_array($qq))
	    {
	    switch($rr['service_type'])
		{
		case 1:
		    $rr['type'] = "Разовая услуга";
		    $tt = $db->fetch_row_array("SELECT cost, 0 as need_del, 0 as expire_date FROM once_service_data WHERE id=".$rr['id']);
		    $rr['cost'] = $tt['cost'];
		    $rr['need_del'] = $tt['need_del'];
		    $rr['expire_date'] = $tt['expire_date'];
		    break;
		case 6:
		    $rr['type'] = "Телефония";
		    break;
		case 2:
    		    $rr['type'] = "Периодическая услуга";
		    break;

		case 3:
		    $rr['type'] = "Интернет";
		    break;
		default:
		    $rr['type'] = "Неизвестный тип услуги";
	    }
        $ip_groups_q = $db->query( "
select 
    ig.id
    , inet_ntoa( 0xffffffff & ig.ip ) ip
    , ig.uname
    , ig.upass
    , ig.mac
    , ts.type
    , ts.netmask
    , ts.gateway
    , ts.dns
    , tnl.id port_link_id
from 
    iptraffic_service_links ipsl 
    join ip_groups ig on ( ig.ip_group_id = ipsl.ip_group_id and ig.is_deleted = 0 )
    left join tt.tlc_subnets ts on ( inet_aton( ts.subnet )= ig.ip & inet_aton( ts.netmask ) )
    left join tt.tlc_nobj_links tnl on ( inet_aton( tnl.port_ip ) = 0xffffffff & ig.ip )
where 
    ipsl.is_deleted = 0
    and ipsl.id = " . $rr['slink_id'] );
        $ip_groups = array();
        while( $ip_group = $db->fetch_array( $ip_groups_q ) )
        {
            $stb = array();
            if( $ip_group[ 'mac' ] + 0 > $MA_net_id + 0 )
            {
                $stb = MA_get_stb_info( $MA_url, $MA_net_id, $MA_key, $vars['external_id']['val'], $ip_group[ 'mac' ] + 0 );
                $stb[ 'id' ] = $ip_group[ 'mac' ] + 0;
            }
            $ip_groups[] = array( 
                'ip_group_id' => $ip_group['id']
                , 'ip' => $ip_group['ip']
                , 'uname' => $ip_group['uname']
                , 'upass' => $ip_group['upass']
                , 'stb' => $stb
                , 'type' => $ip_group['type']
                , 'netmask' => $ip_group['netmask']
                , 'gateway' => $ip_group['gateway']
                , 'dns' => $ip_group['dns']
                , 'port_link_id' => $ip_group['port_link_id']
                );
        }
	    $s[] = array(
	    "slink_id" => $rr['slink_id'],
	    "id" => $rr['id'],
	    "name" => $rr['service_name'],
	    "default" => $rr['link_by_default'],
	    "type" => $rr['type'],
	    "cost" => $rr['cost'] ,
	    "need_del" => $rr['need_del'],
	    "expire_date" => $rr['expire_date'],
    	    "ip_groups" => $ip_groups,
    	    "parent_service_id" => $rr['parent_service_id']
        );
	}

    unset( $speed_limits );
    $qq = $db->query( "select ho_s, ho_e, boi1, boo1 from lru_unlim1 where tarif_id=" . $r['id'] );
    while( $rr = $db->fetch_array( $qq ) )
        $speed_limits[] = $rr;

	$t[] = array(
	"tlink_id" => $r['tlink_id'],
	"id" => $r['id'],
	"name" => $r['name'],
	#"credit" => $r['credit'],
	#"periodic" => $r['periodic_type'],
	"services" => $s,
    "speed_limits" => $speed_limits,
    "next_tariff_id" => $r['next_tariff_id']);
    }
    var_set("report", $t);
    return true;
}




function get_user_services()
{
    global $db, $vars;

    // $s= array();
    $sql = "
    
    SELECT  sl.id as slink_id, sd.id, sd.service_name,
    sd.service_type, sd.link_by_default, psd.cost, psl.need_del, psl.expire_date, psl.start_date, 
    
    case sd.id
    when 774 then (select concat('<a href=\"',link,'\">',CAST('скачать' AS CHAR CHARACTER SET utf8),'</a>') from drweb_data where slink_id=sl.id)
    when 775 then (select concat('<a href=\"',link,'\">',CAST('скачать' AS CHAR CHARACTER SET utf8),'</a>') from drweb_data where slink_id=sl.id)
    when 776 then (select concat('<a href=\"',link,'\">',CAST('скачать' AS CHAR CHARACTER SET utf8),'</a>') from drweb_data where slink_id=sl.id)
    when 777 then (select concat('<a href=\"',link,'\">',CAST('скачать' AS CHAR CHARACTER SET utf8),'</a>') from drweb_data where slink_id=sl.id)
    when 815 then (select concat('<a href=\"',link,'\">',CAST('скачать' AS CHAR CHARACTER SET utf8),'</a>') from drweb_data where slink_id=sl.id)
    when 333 then 'Рассылка СМС Уведомлений'
    
when 1084 then (
select GROUP_CONCAT('IP',': ',inet_ntoa( 0xffffffff & ig.ip ),' - ','Логин',': ',ig.uname  separator '</br>')  
from service_links sl, services_data sd, iptraffic_service_links ipsl, ip_groups ig, accounts a  
where   sl.id=ipsl.id and sl.account_id=a.id and sl.service_id=sd.id and ipsl.ip_group_id =ig.ip_group_id 
and sl.is_deleted=0 and ipsl.is_deleted=0 and sd.is_deleted=0 and ig.is_deleted=0 and a.is_deleted=0  
and sd.parent_service_id=607  
and inet_ntoa( 0xffffffff & ig.ip ) not like '10.%' 
and inet_ntoa( 0xffffffff & ig.ip ) not like '192.%' 
and inet_ntoa( 0xffffffff & ig.ip ) not like '172.%' 
and  a.external_id=".$vars['external_id']['val']." )    
    
    END
    as comment
        
    FROM services_data AS sd, periodic_services_data AS psd ,service_links sl, periodic_service_links psl
    WHERE
    sd.tariff_id=0 and psd.id=sd.id and sl.service_id=sd.id and sl.id=psl.id
    and psd.is_deleted=0 and psl.is_deleted=0 and sd.is_deleted=0 and sl.is_deleted=0
    and sl.account_id in (SELECT a.id from accounts a WHERE a.external_id=".$vars['external_id']['val']." AND a.is_deleted=0)
    
    ";


    //echo $sql;
    $q = $db->query($sql);
    	while($r = $db-> fetch_array($q))
	{
	// echo $r;
	$s[] = array(
	"id" => $r['id'],
	"slink_id" => $r['slink_id'],
	"service_name" => $r['service_name'],
	"service_type" => $r['service_type'],
	"default" => $r['link_by_default'],
	"need_del" => $r['need_del'],
	"expire_date" => $r['expire_date'],
	"cost" => $r['cost'],
	"comment" => $r['comment'],
	"start_date" => $r['start_date']
	);
    }
    var_set("report", $s);
    return true;
}



function get_user_next_tariffs()

{
    global $db, $vars, $ret;


    $sql = "
    SELECT t.id, t.name, t.comments, (LAST_DAY(NOW()) + INTERVAL 1 DAY - INTERVAL 0 MONTH) as start_date
    FROM tariffs t, account_tariff_link atl, accounts a
    where t.id=atl.next_tariff_id and atl.is_deleted=0 and atl.id=".$vars['tlink_id']['val']." and atl.next_tariff_id!=atl.tariff_id
    and atl.account_id =a.id and a.external_id=".$vars['external_id']['val']." AND a.is_deleted=0


    ";
    //echo $sql;
    $q = $db->query($sql);
    if(!$q) return $db->error;
    $t = array();
    while($r = $db->fetch_array($q))
	{
	$s = array();
	$sql = "


	SELECT sl.id as slink_id,sd.id, sd.service_name, sd.link_by_default, sd.service_type, psd.cost, sd.parent_service_id
	FROM tariffs_services_link AS tsl, services_data AS sd, periodic_services_data AS psd ,service_links sl
	WHERE  psd.id=sd.id and tsl.is_deleted=0 AND tsl.tariff_id=".$r['id']." AND sd.id=tsl.service_id AND sd.is_deleted=0
	and sl.is_deleted=0 and sl.service_id=sd.id and sl.account_id in (SELECT a.id from accounts a
	WHERE a.external_id=".$vars['external_id']['val']." AND a.is_deleted=0)

	";

	$qq = $db->query($sql);
	while($rr = $db->fetch_array($qq))
	{
	    switch($rr['service_type'])
	    {
		case 1:
		$rr['type'] = "Разовая услуга";
		$tt = $db->fetch_row_array("SELECT cost FROM once_service_data WHERE id=".$rr['id']);
		$rr['cost'] = $tt['cost'];
	        break;
		case 6:
		$rr['type'] = "Телефония";
		break;
		case 2:
		$rr['type'] = "Периодическая услуга";
		break;

		case 3:
		$rr['type'] = "Интернет";
		break;
		default:
		$rr['type'] = "Неизвестный тип услуги";
	    }
	    $s[] = array(
	    "slink_id" => $rr['slink_id'],
	    "id" => $rr['id'],
	    "name" => $rr['service_name'],
	    "default" => $rr['link_by_default'],
	    "type" => $rr['type'],
	    "cost" => $rr['cost'],
	    "parent_service_id" => $rr['parent_service_id']
	    );
	}

	$t[] = array(
	"id" => $r['id'],
	"name" => $r['name'],
	"start_date" => $r['start_date'],
	#"credit" => $r['credit'],
	#"periodic" => $r['periodic_type'],
	"services" => $s);
	#"start_date" => $r['start_date'];
    }
    var_set("report", $t);
    return true;
}



function get_report_payments()
{
	global $db, $vars;

	$r = $db->fetch_row_array("SELECT id FROM accounts WHERE external_id=".$vars['external_id']['val']." AND is_deleted=0");
	if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];
	$account_id = $r['id'];
	
	$tb = mktime(0,0,0,$vars['month']['val'],1,$vars['year']['val']);
	$te = mktime(0,0,0,$vars['month']['val']+1,1,$vars['year']['val']);

	$payments = array();

	$sql = "
 SELECT DATE_FORMAT(FROM_UNIXTIME(actual_date),'%Y-%m-%d') as mdd, truncate(payment_incurrency,2) as ssum, method as srv, comments_for_user comment, comments_for_admins admin_comment
 FROM ".get_dtable(($tb+$te)/2, 7)."
 WHERE account_id=".$account_id." AND payment_enter_date BETWEEN ".$tb." AND ".$te." AND payment_incurrency!=0";
/*    $sql .= "
    UNION ALL
 SELECT DATE_FORMAT(FROM_UNIXTIME(actual_date),'%Y-%m-%d') as mdd, truncate(payment_incurrency,2) as ssum, method as srv, comments_for_user comment, comments_for_admins admin_comment
  FROM ".get_dtable(($tb+$te)/2+date('t',($tb+$te)/2)*24*3600, 7)."
       WHERE account_id=".$account_id." AND actual_date BETWEEN ".$tb." AND ".$te." AND payment_incurrency!=0
    ";
    $sql .= "
    UNION
 SELECT DATE_FORMAT(FROM_UNIXTIME(actual_date),'%Y-%m-%d') as mdd, truncate(payment_incurrency,2) as ssum, method as srv, comments_for_user comment, comments_for_admins admin_comment
  FROM ".get_dtable(($tb+$te)/2-date('t',($tb+$te)/2)*24*3600, 7)."
       WHERE account_id=".$account_id." AND actual_date BETWEEN ".$tb." AND ".$te." AND payment_incurrency!=0
        ORDER BY mdd
    ";*/
// AND method!=7
	$q = $db->query($sql);
	while($r = $db->fetch_array($q))
	{
		$payments[] = array(
			"date" => $r['mdd'],
			"type" => get_tar_text($r['srv'], -1),
			"summ" => $r['ssum'],
            "comment" => $r['comment'],
            "admin_comment" => $r['admin_comment']
			);
	}
	var_set('report', $payments);
	return true;
}

function get_report_traffic()
{
	global $db, $vars;

	$r = $db->fetch_row_array("SELECT id FROM accounts WHERE external_id=".$vars['external_id']['val']." AND is_deleted=0");
	if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];
	$account_id = $r['id'];
	
	if(!$vars['day']['val'])
	{
		$tb = mktime(0,0,0,$vars['month']['val'],1,$vars['year']['val']);
		$te = mktime(0,0,0,$vars['month']['val']+1,1,$vars['year']['val']);
	} else {
		$tb = mktime(0,0,0,$vars['month']['val'],$vars['day']['val'],$vars['year']['val']);
		$te = mktime(0,0,0,$vars['month']['val'],$vars['day']['val']+1,$vars['year']['val']);
	}

	var_set('report', get_traff($account_id, $tb, $te, $vars['day']['val']));
	#var_set('report', get_traff($account_id, $tb, $te, ''));
	return true;
}

function get_report_main()
{
	global $db, $vars;

	$r = $db->fetch_row_array("SELECT id FROM accounts WHERE external_id=".$vars['external_id']['val']." AND is_deleted=0");
	if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];
	$account_id = $r['id'];
	
	$tb = mktime(1,0,0,$vars['month']['val'],1,$vars['year']['val']);
	$te = mktime(0,59,59,$vars['month']['val']+1,1,$vars['year']['val']);

	$rep = array();

	// Начало периода
	$r = $db->fetch_row_array("SELECT round(floor(out_balance*100)/100,2) AS ssum, DATE_FORMAT(FROM_UNIXTIME(date),'%Y-%m-%d') 
	AS mdd FROM balance_history WHERE account_id=".$account_id." AND  date < $te order by date desc limit 1" );
	if(is_array($r)) $rep[] = array('date'=>$r['mdd'], 'type'=>'Входящий остаток','comment'=>'','summ'=>$r['ssum']);
	
	// Период
	$sql = "
 SELECT *
 FROM (
	SELECT DATE_FORMAT(FROM_UNIXTIME(min(discount_date)),'%Y-%m-%d') AS mdd, round( floor( -sum( discount ) * 100 ) / 100, 2 ) AS ssum, service_id AS srv, charge_type AS srvt, count(1) count
	FROM ".get_dtable(($tb+$te)/2, 1)."
	WHERE account_id=".$account_id." AND discount_date BETWEEN ".$tb." AND ".$te." AND service_type != 0 AND discount !=0
	GROUP BY DATE_FORMAT(FROM_UNIXTIME(discount_date),'%Y-%m-%d'), service_id, service_type, charge_type
	UNION ALL
	SELECT DATE_FORMAT(FROM_UNIXTIME(min(actual_date)),'%Y-%m-%d') AS mdd, round( floor( sum( payment_incurrency ) * 100 ) / 100, 2) AS ssum, method AS srv, -1 AS srvt, count(1) count
	FROM ".get_dtable(($tb+$te)/2, 7)."
	WHERE account_id=".$account_id." AND actual_date BETWEEN ".$tb." AND ".$te." AND payment_incurrency !=0 AND method != 7
	GROUP BY DATE_FORMAT(FROM_UNIXTIME(actual_date),'%Y-%m-%d'), method
	) AS tt
 ORDER BY tt.mdd ";

	$q = $db->query($sql);
	while($r = $db->fetch_array($q))
	{
		$rep[] = array(
			'date'=>$r['mdd'], 
			'type'=>get_type_name($r['srvt']),
			'comment'=>get_tar_text($r['srv'], $r['srvt']),
            'count' => $r['count'],
			'summ'=>$r['ssum']);
	}

	$r = $db->fetch_row_array("
	
	SELECT round(floor(out_balance*100)/100,2) AS ssum, DATE_FORMAT(FROM_UNIXTIME(date),'%Y-%m-%d')
	AS mdd FROM balance_history WHERE account_id=".$account_id." AND  date > $te order by date limit 1"
	
	);
	if(!is_array($r)) $r = $db->fetch_row_array("SELECT round(floor(balance*100)/100,2) AS ssum, DATE_FORMAT(FROM_UNIXTIME(".time()."),'%Y-%m-%d') AS mdd FROM accounts WHERE id=".$account_id);
	$rep[] = array('date'=>$r['mdd'], 'type'=>'Исходящий остаток','comment'=>'','summ'=>$r['ssum']);
	
	var_set('report', $rep);
	return true;
}

function get_new_users()
{
	global $db,$vars;
	
	if($vars['firm']['val'] != "") $sql = "
SELECT a.external_id
 FROM users AS u, users_accounts AS ua, accounts AS a, user_additional_params AS uap
 WHERE u.create_date >= ".$vars['date_from']['val']." AND u.create_date <= ".$vars['date_to']['val']." AND u.is_deleted=0
  AND ua.uid=u.id AND ua.is_deleted=0 AND u.is_juridical=0
  AND a.id=account_id AND a.is_deleted=0
  AND uap.userid=u.id AND uap.paramid=2 AND uap.value LIKE '".$vars['firm']['val']."'";
	else $sql = "
SELECT a.external_id
 FROM users AS u, users_accounts AS ua, accounts AS a
 WHERE u.create_date >= ".$vars['date_from']['val']." AND u.create_date <= ".$vars['date_to']['val']." AND u.is_deleted=0
  AND ua.uid=u.id AND ua.is_deleted=0 AND u.is_juridical=0
  AND a.id=account_id AND a.is_deleted=0";
	$e = array();
	$q = $db->query($sql);
	while($r = $db->fetch_array($q)) $e[] = $r['external_id'];
	
	var_set('report', $e);
	return true;
}

function get_promised_payments()
{
	global $db,$vars;

	$t = "";
	if($vars['date_from']['val']) $t.= " AND lc.ch_date>=".$vars['date_from']['val'];
	if($vars['date_to']['val']) $t.= " AND lc.ch_date<=".$vars['date_to']['val'];
	$sql = "
SELECT lc.credit, lc.ch_date, lc.status
 FROM lru_credit AS lc, accounts AS a
 WHERE a.external_id=".$vars['external_id']['val']." AND a.is_deleted=0 AND lc.account_id=a.id ".$t."
 ORDER BY ch_date";

	$e = array();
	$q = $db->query($sql);
	while($r = $db->fetch_array($q)) $e[] = array('date_to'=>$r['ch_date'], 'promised_payment'=>$r['credit'], 'status'=>$r['status']);

	var_set('report', $e);
	return true;
}

function get_firm_data()
{
	global $db,$vars;

	$r = $db->fetch_row_array("SELECT * FROM lru_firms WHERE name='".$vars['firm']['val']."'");

	var_set('report', $r);
	return true;
}

function get_geo_objects()
{
	global $db, $vars;
	// Вывдаем адреса только назначенные фирме ЛЮБИ
	$firm_id = 2;

	$pid = !$vars['geo_id']['val'] ? "IS NULL" : "= ".$vars['geo_id']['val'];
	
	if ($vars['geo_id']['val'] ==2) 
	
	 { $pid =  'in (3,4,5,345)'; } else {}
	// echo 123;
	$r1 = $db->query("set character set latin1");
	 
########$sql = "SELECT tgo.id, tgt.name AS type, tgt.short_name AS type_short, tgo.name 
########FROM tt.tlc_geo_objects AS tgo, tt.tlc_geo_types AS tgt 
########WHERE tgo.parent_id ".$pid." AND tgt.id=tgo.geo_type_id AND tgo.status=0 
########AND tgo.id not in (633,345,480) order by type_short";

	$sql = "SELECT 
                    tgo.id
                    , tgt.name AS type
                    , tgt.short_name AS type_short
                    , tgo.name
                FROM 
                    tt.tlc_geo_objects AS tgo 
                    join tt.tlc_geo_types tgt on tgt.id=tgo.geo_type_id         
                WHERE  
                    tgo.parent_id $pid 
                    AND
                     tgo.status = 0         
                    AND tgo.id not in (633,345,480) 
                    and (
                         ( select 1 from tt.tlc_geo_groups_content tggc 
                                         join tt.tlc_geo_groups_firms tggf on tggf.group_id=tggc.group_id 
                           where tggf.firm_id = $firm_id and tggc.geo_id = tgo.id limit 1 )
                         or ( select 1 from tt.tlc_geo_objects tgo1 
                                            join tt.tlc_geo_groups_content tggc on tggc.geo_id = tgo1.id 
                                            join tt.tlc_geo_groups_firms tggf on tggf.group_id = tggc.group_id 
                             where tggf.firm_id = $firm_id and tgo1.parent_id = tgo.id limit 1 )
                         or ( select 1 from tt.tlc_geo_objects tgo2 
                                            join tt.tlc_geo_objects tgo1 on tgo1.id = tgo2.parent_id 
                                            join tt.tlc_geo_groups_content tggc on tggc.geo_id = tgo2.id 
                                            join tt.tlc_geo_groups_firms tggf on tggf.group_id = tggc.group_id 
                              where tggf.firm_id = $firm_id and tgo1.parent_id = tgo.id limit 1 )
                         or ( select 1 from tt.tlc_geo_objects tgo3
                                            join tt.tlc_geo_objects tgo2 on tgo2.id = tgo3.parent_id
                                            join tt.tlc_geo_objects tgo1 on tgo1.id = tgo2.parent_id 
                                            join tt.tlc_geo_groups_content tggc on tggc.geo_id = tgo2.id 
                                            join tt.tlc_geo_groups_firms tggf on tggf.group_id = tggc.group_id 
                              where tggf.firm_id = $firm_id and tgo1.parent_id = tgo.id limit 1 )
                         )
                order by tgo.name";

	$e = array();
	$q = $db->query($sql);
	while($r = $db->fetch_array($q)) $e[] = $r;

	var_set('report', $e);
	return true;
}

function get_house_id()
{
	global $db, $vars;
	$city = $vars['city']['val'];
	$street = $vars['street']['val'];
	$house = $vars['house']['val'];
	$building_text = $vars['building_text']['val'];

	$r = $db->fetch_row_array( "select id from houses where city=\"$city\" and street=\"$street\" and number=\"$house\" and building=\"$building_text\" limit 1" );
	if( ! $r['id'] ) return "Can't find city $city street $street house $house building $building_text.";
	var_set( 'house_id', $r['id'] );
	return true;
}

function get_next_ext_id()
{
    global $db, $vars, $db_base;
    $firm = $vars['firm']['val'];
    $house_id = $vars['house_id']['val'];


    $pool213 = array();
    for( $i = 477 ; $i <= 495; ++$i )
        $pool213[] = $i;
    // номера, которые не выдавать
    $excluded = array();
    for( $i = 21200500 ; $i <= 21200515 ; ++$i )
        $excluded[] = $i;

    array_push( $excluded, 21200054, 21200214, 21200288 );

    if( $firm == 'zzzz' && ! $house_id ) return "Need house_id for firm $firm.";
    if( ! in_array( $firm, array( 'xxxxx', 'zzzzzz' )  ) ) return "Firm $firm not implemented yet.";

    switch( $firm )
    {
        case 'xxxxxxx': 
            $r = $db->fetch_row_array( "SELECT Auto_increment acc_id FROM information_schema.tables WHERE table_name='accounts' and TABLE_SCHEMA = '$db_base'" );
            if( ! $r['acc_id'] ) return "Unknown error while searching external_id for firm $firm";
            var_set( 'external_id', sprintf( "101%05d", $r['acc_id'] ) );
            break;
        case 'zzzzz': 
            if( in_array( $house_id, $pool213 ) )
            {
                $start = 21300001;
                $end = 21399999;
                $sql = "select external_id from accounts where external_id like '213%' and is_deleted = 0";
            }
            else
            {
                $start = 21200001;
                $end = 21299999;
                $sql = "select external_id from accounts where external_id like '212%' and is_deleted = 0";
            }
            $q = $db->query( $sql );
            while( $r = $db->fetch_array( $q ) )
                $used[ $r['external_id'] ] = 1;
            for( $i = $start; $i <= $end ; ++$i )
               if( ! $used[ $i ] && ! in_array( $i, $excluded ) )
               {
                   var_set( 'external_id', $i );
                   return true;
               }
            return "Can't find free external_id for firm $firm and house_id $house_id.";
            break;
    }
    return true;
}

function get_port_info()
{
    global $db, $vars;
    $port_link_id = $vars['port_link_id']['val'];
    $sql = "select l.port_id port, l.port_access_id access_id, l.port_ip ip, c.ip host_ip, c.snmp_comm_w community from tt.tlc_nobj_links l join tt.tlc_nobj_logical c using( ldev_id ) where l.id = $port_link_id";
    $r = $db->fetch_row_array( $sql );
    if( ! is_array( $r ) )
        return "Can't find port_link_id $port_link_id";
	$ip = $r['ip'];
	$host_ip = $r['host_ip'];
	$host_community = $r['community'];
	$port = $r['port'];
    $access_id = $r["access_id"];
    $e = array();

	$dev = new snmplib($host_ip,$host_community);
	
    $cable = $dev->get_cable_status($port);

	if( is_array( $cable ) )
	{
		$t = "Статус порта $host_ip : $port &nbsp&nbsp&nbsp " .$cable[0]."<br><br>";
		$t .= "Длина 1 пары &nbsp&nbsp&nbsp ".$cable[1]."м<br>";
		$t .= "Длина 2 пары &nbsp&nbsp&nbsp ".$cable[2]."м<br>";
		$t .= "Длина 3 пары &nbsp&nbsp&nbsp ".$cable[3]."м<br>";
		$t .= "Длина 4 пары &nbsp&nbsp&nbsp ".$cable[4]."м";
	}
	else 
	{
		$t = "<font color='red'><b>Нет доступа к оборудованию $host_ip</b></font>";
        var_set( 'report', array( $t ) );
        return true;
	}

    $e[] = $t;
	$e[] = sprintf( "Ошибки на входе в порт: %s", $dev->get_if_errors( $port ) );

	$macs = $dev->get_mac_on_port( $port );
	$t = "Мак адреса на порту:";
    if( is_array( $macs) )
        foreach( $macs as $mac )
            $t .= "<br>$mac";

    $e[] = $t;
    $rule_out = $dev->get_rule_data( 10, $access_id );
    $rule_in = $dev->get_rule_data( 14, $access_id );
    if( $rule_out['src_ip'] ) 
        $t = sprintf( "Разрешающее правило номер %s ip %s скорость %s", $access_id, $rule_out['src_ip'], $rule_out['rate'] ? ( $rule_out['rate'] . " Kbit/s" ) : "неограничена" );
    else
    {
        $rule_out = $dev->get_rule_data( 4, $access_id );
        if( $rule_out['src_ip'] ) 
            $t = sprintf( "Разрешающее правило номер %s ip %s скорость %s", $access_id, $rule_out['src_ip'], $rule_out['rate'] ? ( $rule_out['rate'] . " Kbit/s" ) : "неограничена" );
        else
            $t = sprintf( "Отсутствует разрешающее правило!" );
    }
    if( $rule_in['dst_ip'] )
        $t .= sprintf( "<br>Ограничивающее правило номер %s ip %s скорость %s", $access_id, $rule_in['dst_ip'], $rule_in['rate']? ( $rule_in['rate'] . " Kbit/s" ) : "неограничена" );
    else
    {
        $rule_in = $dev->get_rule_data( 5, $access_id );
        if( $rule_in['dst_ip'] )
            $t .= sprintf( "<br>Ограничивающее правило номер %s ip %s скорость %s", $access_id, $rule_in['dst_ip'], $rule_in['rate']? ( $rule_in['rate'] . " Kbit/s" ) : "неограничена" );
        else
            $t .= sprintf( "<br>Отсутствует ограничивающее правило!" );
    }
    $e[] = $t;

    #exec( "traceroute -f 1 -q 1 -m 2 -N 1 -n $ip | grep -v traceroute | awk '{ print( $2 );}'", $x_host );
    #$x_host = $x_host[0];
    $action = "/usr/local/bin/my_arping -w2 $ip";

    #print( "/usr/bin/ssh -i /www/vhost/rauthk.key -l rdiag $x_host " . $action );
    #exec( "/usr/bin/ssh -i /www/vhost/rauthk.key -l rdiag $x_host " . $action, $out );
    exec( $action, $out );
    $t = "";
    foreach($out as $line)
        $t .= "<br>$line";
    $e[] = $t;
    var_set( 'report', $e );
    return true;
}

function get_tariffs_history()
{
    global $db, $vars;
    $external_id = $vars['external_id']['val'];
    $sql = "
select 
    th.tariff_id t_id
    , th.tariff_name t_name
    , th.link_date date_from
    , th.unlink_date date_to
    , sa.login who_add 
    , sa1.login who_del 
from 
    users u 
    join accounts a on a.id = u.basic_account and a.is_deleted = 0
    join tariffs_history th on th.account_id = a.id 
    left join user_log ul on ul.user_id = u.id and ul.date = th.link_date and ul.what like 'link tariff %' 
    left join system_accounts sa on sa.id = ul.who and sa.is_deleted = 0
    left join user_log ul1 on ul1.user_id = u.id and ul1.date = th.unlink_date and ul1.what like 'unlink tariff %' 
    left join system_accounts sa1 on sa1.id = ul1.who and sa1.is_deleted = 0
where 
    u.is_deleted = 0
    and a.external_id = '$external_id'";
    $ret = array();
    $q = $db->query( $sql );
    while( $r = $db->fetch_array( $q ) )
        $ret[] = $r;
    var_set( 'report', $ret );
    return true;
}

function get_friends()
{
    global $db, $vars;
    $external_id = $vars['external_id']['val'];
    $sql = "
    select count(id) count, ifnull(sum(summ),0) summ from friends where status=1 and (friend_2=".$external_id." or friend_1=".$external_id.")
	";
    $ret = array();
    $q = $db->query( $sql );
    while( $r = $db->fetch_array( $q ) )
        $ret[] = $r;
	var_set( 'report', $ret );
    return true;
}
    


function get_recommended_payment()
{
global $db, $vars;

$r = $db->fetch_row_array("
select a.id, a.external_id, floor(a.balance) as balance,  round(a.credit,2) as credit   
from accounts a where a.is_deleted=0 and a.external_id=".$vars['external_id']['val']."  and a.id not in 
(select account_id from account_tariff_link where next_tariff_id in (271,408,409,508,375,377,376,499) and is_deleted=0) 

");
$balance= $r['balance'];

if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];



$r1 = $db->fetch_row_array("select ifnull(sum(psd.cost),0) as s from service_links sl,
services_data sd, periodic_services_data psd where sl.service_id=sd.id
and sd.id=psd.id and sd.is_deleted=0 and sl.is_deleted=0 and psd.is_deleted=0
and (sd.tariff_id=0 or sd.link_by_default=0) and sl.account_id=".$r['id']." ");
$sum1= $r1['s'];

$r2 = $db->fetch_row_array("select ifnull(sum(psd.cost),0) as s from services_data sd, periodic_services_data psd, account_tariff_link atl
where sd.id=psd.id and sd.is_deleted=0 and psd.is_deleted=0 and atl.is_deleted=0
and sd.link_by_default=1 and sd.service_type!=1 and sd.tariff_id=atl.next_tariff_id and atl.account_id=".$r['id']." ");
$sum2= $r2['s'];


$sum=$sum1+$sum2;

//echo "$sum </br>";
//echo "$balance </br>";

if ($sum >0)
    {
    
    $summ=$sum - $balance;
    if ($summ >0)
	{
	var_set('summ', $summ);
    
	}
    }
  return true;
}
  


function get_block_history()
{
    global $db, $vars;
    $external_id = $vars['external_id']['val'];
    $sql = "
select 
    if( bi.block_type = 2, 'Административная', 'Системная' ) type
    , bi.start_date date_from
    , if( bi.expire_date = 2000000000, 'активна', bi.expire_date ) date_to
    , bi.is_deleted is_deleted 
from 
    accounts a 
    join blocks_info bi on bi.account_id = a.id 
where 
    a.is_deleted = 0 
    and a.external_id = '$external_id'
    ";
    $ret = array();
    $q = $db->query( $sql );
    while( $r = $db->fetch_array( $q ) )
        $ret[] = $r;
    var_set( 'report', $ret );
    return true;
}


function get_users_status_report()
{
global $db, $vars;

$date_from = $vars['date_from']['val'];
$date_to = $vars['date_to']['val'];
$firm  = $vars['firm']['val'];

/*($firm = "") 

{
$firm='ТЕЛИНКОМ';
}
*/

$date_from = date("Y-m-d", $date_from);
$date_to = date("Y-m-d", $date_to);

$start = $date = $date_from;
$end = $date_to;
$i = 0;
$dates = array();
$report = array();
  while ($date != $end && $start <= $end)
    {
    $dates[] = $date = date('Y-m-d', strtotime($start . ' + ' . $i++ . ' day'));
    }
    for ($i=0; $i<count($dates);$i++)
    {
        //echo '1'; 
        //echo "$dates[$i] \n <br> ";
        $d= strtotime($dates[$i]);
	//добавляем 12 часов, т.к. данные нужны с 12:00
	$d="$d+43200";
	//echo "$d \n <br> ";
	
	$q = $db->query("
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id and u.is_juridical=0 and u.create_date <".$d."
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.account_id not in(select distinct account_id from blocks_info where ".$d." between start_date and expire_date)
#Найти абонентов  заблокированных в дату ".$d." в 12:00 с диапазоном блокировки 1-7 дней
union all
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.is_juridical=0 and u.create_date <".$d." and ".$d." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date > ".$d."-7*(86400) and bi.block_type=1
union all
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.is_juridical=0 and u.create_date <".$d." and ".$d." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date > ".$d."-14*(86400) and bi.start_date <=".$d." -8*(86400) and bi.block_type=1
union all
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.is_juridical=0 and u.create_date <".$d." and ".$d." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date > ".$d."-30*(86400) and bi.start_date <=".$d." -15*(86400) and bi.block_type=1
union all
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.is_juridical=0 and u.create_date <".$d." and ".$d." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date > ".$d."-60*(86400) and bi.start_date <=".$d." -31*(86400) and bi.block_type=1
union all
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.is_juridical=0 and u.create_date <".$d." and ".$d." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date > ".$d."-90*(86400) and bi.start_date <=".$d." -61*(86400) and bi.block_type=1
union all
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.is_juridical=0 and u.create_date <".$d." and ".$d." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date > ".$d."-120*(86400) and bi.start_date <=".$d." -91*(86400) and bi.block_type=1
union all
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.is_juridical=0 and u.create_date <".$d." and ".$d." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date > ".$d."-150*(86400) and bi.start_date <=".$d." -121*(86400) and bi.block_type=1
union all
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.is_juridical=0 and u.create_date <".$d." and ".$d." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date > ".$d."-180*(86400) and bi.start_date <=".$d." -151*(86400) and bi.block_type=1
union all
SELECT  count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.is_juridical=0 and u.create_date <".$d." and ".$d." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date  <=".$d." -180*(86400) and bi.block_type=1
	");
	echo $sql;
	$rr = array();
	    while( $r = $db->fetch_array( $q ) )
	    {
	    $rr[] = $r['count'];
	    }
        $report[ $dates[$i] ] = $rr;
	    //print_r($report);
	    //echo  "<pre>".var_export($report,true)."</pre>";
	}
	var_set( 'report', $report );	
    
return true;
}


function get_upravlen_report()
{
global $db, $vars;

$year = $vars['year']['val'];
$month = $vars['month']['val'];
$firm  = $vars['firm']['val'];


$date_0 = "$year"."-"."$month"."-"."01 00:00:00";
$date_1=  strtotime("+ 0 months",strtotime($date_0));
$date_2=  strtotime("+ 1 months",strtotime($date_0));

//$date_3 = $date_1;
$date_3 = ( $date_1 + $date_2 ) / 2;


#$date_2 = "$year"."-"."$month"."-"."01 00:00:00";

#echo $date_1;

$disc_arch_table = $db->fetch_row_array( "select table_name from archives where table_type = 1 and $date_3 between start_date and end_date" );

//$disc_arch_table = $db->fetch_row_array( "select table_name from archives where table_type = 1 and $date_3 between start_date and end_date" );

if(!is_array($disc_arch_table))
{
$disc_arch_table ='discount_transactions_all';
}  
else
{
$disc_arch_table = $disc_arch_table[ 'table_name' ];
}


$disc_arch_table_temp=$disc_arch_table._temp;

//echo $disc_arch_table_temp;

$sql00=("DROP TABLE IF EXISTS ".$disc_arch_table_temp."");
//echo $sql00;
$q00 = $db->query( $sql00 );

$sql01=("create  table ".$disc_arch_table_temp." select * from ".$disc_arch_table." where charge_type<7 and discount >0");
//echo $sql01;
$q01 = $db->query( $sql01 );


//$r1 = $db->query("create temporary table ".$disc_arch_table_temp." select * from ".$disc_arch_table." where charge_type<7 and discount >0 ");

//echo $r1;

$sql1="select '1' as rep,'2' as count
union all
select '3' as rep,'4' as count";

$sql="
SELECT  concat('Количество действующих абонентов' 
, case when u.is_juridical = '0' then ' ФЛ'
when u.is_juridical = '1' then ' ЮЛ' end
)
as 'rep', count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id and u.create_date <".$date_2."
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.account_id not in(select distinct account_id from blocks_info where ".$date_2." between start_date and expire_date)
group by rep

union all

SELECT  concat('Не активных абонентов за период, шт.'
, case when u.is_juridical = '0' then ' ФЛ'
when u.is_juridical = '1' then ' ЮЛ' end
)
as 'rep', count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and  u.create_date <".$date_2." and ".$date_2." between bi.start_date and bi.expire_date
and bi.is_deleted=0 and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date  >".$date_2." -180*(86400) and bi.block_type=1
group by rep
union all

SELECT  concat('Абоненты к отключению, шт.'
, case when u.is_juridical = '0' then ' ФЛ'
when u.is_juridical = '1' then ' ЮЛ' end
)
 as 'rep', count(distinct a.id) as count from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id
and a.is_deleted=0 and u.create_date <".$date_2." and ".$date_2." between bi.start_date and bi.expire_date
and uap.userid=u.id and uap.paramid=2 and uap.value='".$firm."'
and bi.start_date  <".$date_2." -180*(86400) and bi.block_type=1
group by rep
union all

select concat('Подключения абонентов Интернет'
,case when u.is_juridical = '0' then ' ФЛ'
when u.is_juridical = '1' then ' ЮЛ' end
) as 'rep', count(a.id) as count
from accounts a, users u, blocks_info bi, user_additional_params uap
where a.id=u.basic_account and a.id=bi.account_id and  uap.userid=u.id
# у кого была админская блокировка 
and bi.block_type=2
and bi.id=(select min(id) from blocks_info where block_type=2 and account_id=bi.account_id )
# и она уже удалена
and bi.is_deleted=1

and uap.paramid=2 and uap.value='".$firm."'
and expire_date >=".$date_1."
and expire_date  <".$date_2."
group by rep

union all
select 'Отток абонентов Интернет(ФЛ)' as 'rep', 'нет данных' as count 
union all
select 'Отток абонентов Интернет(ЮЛ)' as 'rep', 'нет данных' as count 

";
$q = $db->query($sql);
while($r = $db-> fetch_array($q))
{
$report[] = array(
"rep" => $r['rep'],
"count" => $r['count']
);
}

$sql="select concat(case
when ltg.name = 'Основная' then 'Начисления по тарифам Интернет'
when ltg.name = 'Общежитие' then 'Начисления по тарифам Интернет'
when ltg.name = 'DUMMY' then 'Начисления по тарифам Интернет'
when ltg.name = 'Автоматические' then 'Начисления по тарифам Интернет'
when ltg.name = 'Новогорск' then 'Начисления по тарифам Интернет'
when ltg.name = 'Школа' then 'Начисления по тарифам Интернет'
when ltg.name = 'ТВ' then 'Начисления по тарифам Телевидение'
when ltg.name = 'Телефония' then 'Начисления по тарифам Телефония'
end
,case when u.is_juridical = '0' then ' ФЛ'
when u.is_juridical = '1' then ' ЮЛ' end
) AS 'rep'
, round(sum(dt.discount_with_tax), 2) AS 'count'
from ".$disc_arch_table_temp." dt
join accounts a on a.id=dt.account_id and a.is_deleted=0
join users u on a.id=u.basic_account and u.is_deleted=0
join user_additional_params uap on u.id=uap.userid and uap.paramid=2 and uap.value='".$firm."'
#join houses h on h.id=u.house_id
join service_links sl on sl.id=dt.slink_id
join account_tariff_link atl on sl.tariff_link_id=atl.id 
join lru_tarif lt on lt.tarif_id=atl.tariff_id
join lru_tarif_group ltg on lt.tarif_group_id=ltg.id
where
dt.discount_date >=".$date_1." and dt.discount_date < ".$date_2."
and dt.charge_type<7  group by  rep  having count>0

";
//echo $sql;
$q = $db->query($sql);
while($r = $db-> fetch_array($q))
{
$report[] = array(
"rep" => $r['rep'],
"count" => $r['count']
);
}

$sql="select concat('Переодические Услуги без аренды'
,case when u.is_juridical = '0' then ' ФЛ'
when u.is_juridical = '1' then ' ЮЛ' end
) AS rep
,round(sum(dt.discount_with_tax), 2)  AS count
from ".$disc_arch_table_temp." dt
join accounts a on a.id=dt.account_id
join users u on a.id=u.basic_account
join user_additional_params uap on u.id=uap.userid and  uap.paramid=2 and uap.value='".$firm."'
join services_data sd on dt.service_id=sd.id

where a.is_deleted=0 and u.is_deleted=0 and dt.charge_type<7
and sd.tariff_id=0 and sd.service_type=2 and sd.service_name not like '%ренда%'
and dt.discount_date >=".$date_1." and dt.discount_date < ".$date_2."
group by rep  having count>0

";
$q = $db->query($sql);
while($r = $db-> fetch_array($q))
{
$report[] = array(
"rep" => $r['rep'],
"count" => $r['count']
);
}

$sql="select concat('Переодические Услуги аренды'
,case when u.is_juridical = '0' then ' ФЛ'
when u.is_juridical = '1' then ' ЮЛ' end
) AS rep
,round(sum(dt.discount_with_tax), 2) AS count
from ".$disc_arch_table_temp." dt
join accounts a on a.id=dt.account_id
join users u on a.id=u.basic_account
join user_additional_params uap on u.id=uap.userid and  uap.paramid=2 and uap.value='".$firm."'
join services_data sd on dt.service_id=sd.id

where a.is_deleted=0 and u.is_deleted=0 and dt.charge_type<7
and sd.tariff_id=0 and sd.service_type=2 and sd.service_name like '%ренда%'
and dt.discount_date >=".$date_1." and dt.discount_date < ".$date_2."
group by rep  having count>0

";
$q = $db->query($sql);
while($r = $db-> fetch_array($q))
{
$report[] = array(
"rep" => $r['rep'],
"count" => $r['count']
);
}

$sql="select concat('Разовые Услуги(продажа, подключения)' 
,case when u.is_juridical = '0' then ' ФЛ'
when u.is_juridical = '1' then ' ЮЛ' end
) AS rep
,round(sum(dt.discount_with_tax), 2) AS count
from users u
join accounts a on a.id=u.basic_account
join user_additional_params uap on u.id=uap.userid and  uap.paramid=2 and uap.value='".$firm."'
join ".$disc_arch_table_temp." dt on dt.account_id=a.id
join services_data sd on dt.service_id=sd.id

where

a.is_deleted=0 and u.is_deleted=0 and dt.charge_type<7
and sd.tariff_id=0 and sd.service_type=1
and discount_date >=".$date_1."
and discount_date < ".$date_2."
group by rep  having count>0


";

//echo $sql;
$q = $db->query($sql);
while($r = $db-> fetch_array($q))
{
$report[] = array(
"rep" => $r['rep'],
"count" => $r['count']
);
}


        var_set( 'report', $report );

$sql2=("drop table ".$disc_arch_table_temp." ");
//echo $sql2;
$q2 = $db->query( $sql2 );


return true;

}




function get_skk_connect_report()
    {
    global $db, $vars;

    $date_start = $vars['date_from']['val'];
    $date_end = $vars['date_to']['val'];

$r1 = $db->query("set character set utf8");
$sql ="select 
j.CODE AS 'code'
#, j.DATEFINISH AS 'call_date'
, j.NEWKLIENT AS 'name'

, j.usercode AS 'usercode'
, bd.VALUESTR AS 'external_id'
, h.code AS 'h_code'
, CONCAT(h.ADR,', <br>кв. ',  b.APART) AS 'adr'

, from_unixtime(bi.expire_date) AS 'connect_date'
, concat(j2.DATEDO, ' --  ' , from_unixtime(unix_timestamp(j2.DATEDO)+5400) ) as 'planed_date'
, CASE 
when bi.expire_date BETWEEN unix_timestamp(j2.DATEDO) AND unix_timestamp(j2.DATEDO)+5400 THEN 'В графике'
ELSE 'Не в графике'
END AS is_late



, CONCAT(IFNULL(a0.VALUESTR,''),'<br>',IFNULL(a5.VALUESTR,'')) as 'data1'
, CONCAT(IFNULL(a1.VALUESTR,''),'<br>',IFNULL(a6.VALUESTR,'')) as 'data2'
, CONCAT(IFNULL(a2.VALUESTR,''),'<br>',IFNULL(a7.VALUESTR,'')) as 'data3'
, CONCAT(IFNULL(a3.VALUESTR,''),'<br>',IFNULL(a9.VALUESTR,'')) as 'data4'
, CONCAT(IFNULL(a4.VALUESTR,''),'<br>',IFNULL(a8.VALUESTR,'')) as 'data5'

, j.OPIS AS 'data6'
, j.PARENTCODE AS 'data7'

,group_concat(inet_ntoa(ipg.ip & 0x00ffffffff) ,' - ', 
(select count(*) from dhcp.dhcpack d where d.Message = (ipg.ip & 0x00ffffffff) 
and unix_timestamp(d.ReceivedAt) between bi.expire_date -43200 and bi.expire_date +43200),'<br>'
) AS 'data8'

,(select group_concat(p.FIO, '<br>') from userside.tbl_journal_staff js, userside.tbl_pers p
where p.CODE =js.PERSCODE and p.CODE not in (10,11,12,70) and js.JOURNALCODE=j2.CODE and js.ISPODRAZD=0
) AS 'data9'

FROM userside.tbl_journal j

join  userside.tbl_base b on b.CODE=j.USERCODE
join  userside.tbl_house h on h.CODE=b.HOUSEC
join  userside.tbl_base_dopdata bd on bd.USERCODE=b.CODE and bd.DATACODE=3


#Таблицы биллинга с абонентами и активацией

#join UTM5.accounts a  on a.external_id=bd.VALUESTR and a.is_deleted=0
#join UTM5.users u on a.id=u.basic_account and u.is_deleted=0
join UTM5.users u on u.id=b.CODETI and u.is_deleted=0
join UTM5.accounts a  on a.id=u.basic_account and a.is_deleted=0


join UTM5.blocks_info bi on bi.account_id=a.id 
and bi.block_type=2 and bi.is_deleted=1
and bi.id=(select min(UTM5.blocks_info.id) from UTM5.blocks_info where UTM5.blocks_info.block_type=2 
AND UTM5.blocks_info.start_date!=UTM5.blocks_info.expire_date and UTM5.blocks_info.account_id=bi.account_id )


join UTM5.service_links sl on sl.account_id=a.id and sl.is_deleted=0
join UTM5.iptraffic_service_links ipsl on ipsl.id=sl.id  and ipsl.is_deleted=0
join UTM5.ip_groups ipg on ipg.ip_group_id=ipsl.ip_group_id and ipg.is_deleted=0



#таблица родительской заявки с временем планированного подключения

left join  userside.tbl_journal j2 on j2.CODE = j.PARENTCODE


#Таблица с (select CODE, NAZV from tbl_conf_attr limit 49,20;)

left join userside.tbl_attr a0 on a0.USERCODE=j.CODE and a0.ATTRCODE=40
left join userside.tbl_attr a1 on a1.USERCODE=j.CODE and a1.ATTRCODE=41
left join userside.tbl_attr a2 on a2.USERCODE=j.CODE and a2.ATTRCODE=42
left join userside.tbl_attr a3 on a3.USERCODE=j.CODE and a3.ATTRCODE=43
left join userside.tbl_attr a4 on a4.USERCODE=j.CODE and a4.ATTRCODE=44
left join userside.tbl_attr a5 on a5.USERCODE=j.CODE and a5.ATTRCODE=45
left join userside.tbl_attr a6 on a6.USERCODE=j.CODE and a6.ATTRCODE=46
left join userside.tbl_attr a7 on a7.USERCODE=j.CODE and a7.ATTRCODE=47
left join userside.tbl_attr a8 on a8.USERCODE=j.CODE and a8.ATTRCODE=48
left join userside.tbl_attr a9 on a9.USERCODE=j.CODE and a9.ATTRCODE=49

where 
j.STATUS=2
and j.TYPER=25

and j2.DATEDO
BETWEEN FROM_UNIXTIME('".$date_start."') AND FROM_UNIXTIME('".$date_end."')+ INTERVAL 1 DAY
group by j.CODE
order by j2.DATEDO
";

//echo $sql;

#$ret = array();
$r = array();
    $q = $db->query($sql);
    while($r = $db-> fetch_array($q))
        {
    //var_dump($r);
    $report[] = array(

"code" => $r['code'],
"name" => $r['name'],
"usercode" => $r['usercode'],
"external_id" => $r['external_id'],
"h_code" => $r['h_code'],
"adr" => $r['adr'],
"connect_date" => $r['connect_date'],
"planed_date" => $r['planed_date'],
"is_late" => $r['is_late'],
"data1" => $r['data1'],
"data2" => $r['data2'],
"data3" => $r['data3'],
"data4" => $r['data4'],
"data5" => $r['data5'],
"data6" => $r['data6'],
"data7" => $r['data7'],
"data8" => $r['data8'],
"data9" => $r['data9']

        );
                
    }
    

    var_set( 'report', $report );
    return true;
                        
}
    
    
function get_tariff_info()
{
    global $db, $vars;
    $tariff_id = $vars['tariff_id']['val'];

    $sql = "
select 
    t.name name
    , t.create_date create_date
    , t.change_date change_date
    , t.expire_date expire_date
    , t.comments comments
    , lt.type type
    , lt.periodic_type periodic_type
    , ltg.name tariff_group 
    , sum( psd.cost ) cost
from 
    tariffs t 
    join lru_tarif lt on lt.tarif_id = t.id 
    join lru_tarif_group ltg on ltg.id = lt.tarif_group_id 
    left join services_data sd on sd.tariff_id = t.id and sd.is_deleted = 0 and sd.link_by_default = 1
    left join periodic_services_data psd on psd.id = sd.id and psd.is_deleted = 0
where 
    t.is_deleted = 0 
    and t.id = $tariff_id";
    unset( $speed_limits );
    $r = $db->fetch_row_array( $sql );
    $qq = $db->query( "select ho_s, ho_e, boi1, boo1 from lru_unlim1 where tarif_id=$tariff_id" );
    while( $rr = $db->fetch_array( $qq ) )
        $speed_limits[] = $rr;
    $r['speed_limits'] = $speed_limits;

    $s = array();
    $sql = "
SELECT sd.id, sd.service_name, sd.link_by_default, sd.service_type, psd.cost, sd.parent_service_id
FROM tariffs_services_link AS tsl, services_data AS sd
LEFT JOIN periodic_services_data AS psd ON psd.id=sd.id
WHERE tsl.is_deleted=0 AND tsl.tariff_id=$tariff_id AND sd.id=tsl.service_id AND sd.is_deleted=0";
    $qq = $db->query($sql);
    while($rr = $db->fetch_array($qq))
    {
        switch($rr['service_type'])
        {
            case 1:
                $rr['type'] = "Разовая услуга";
                $tt = $db->fetch_row_array("SELECT cost FROM once_service_data WHERE id=".$rr['id']);
                $rr['cost'] = $tt['cost'];
                break;
            case 6:
                $rr['type'] = "Телефония";
                break;
            case 2:
                    $rr['type'] = "Периодическая услуга";
                break;
                                                                                                                
            case 3:
                                    $rr['type'] = "Интернет";
                                    break;	
            default:
                $rr['type'] = "Неизвестный тип услуги";
        }
        $s[] = array(
            "id" => $rr['id'],
            "name" => $rr['service_name'],
            "default" => $rr['link_by_default'],
            "type" => $rr['type'],
            "cost" => $rr['cost'],
            "parent_service_id" => $rr['parent_service_id']
            
            );
    }

    $r['services'] = $s;
    var_set( 'report', $r );
    return true;
}

function get_pppoe_log()
{
    global $db, $vars;
    global $db_radius_user, $db_radius_pass, $db_radius_host, $db_radius_base;

    $login = $vars['login']['val'];
    $date_from = $vars['date_from']['val'];
    $date_to = $vars['date_to']['val'];

    // Подключаем БД сессий
    $db_radius = new db( $db_radius_host, $db_radius_user, $db_radius_pass, $db_radius_base );
    if( !$db_radius->connect() ) return $db->error;

    $sql = "
select 
    rp.user
    , rp.reply
    , ra.NASIPAddress
    , rp.date
    , ra.AcctStopTime
    , ra.AcctSessionTime
    , ra.AcctInputOctets
    , ra.AcctOutputOctets
    , ra.CalledStationId
    , ra.CallingStationId
    , ra.AcctTerminateCause
    , ra.FramedIPAddress 
from 
     radpostauth rp
     left join radacct ra on ra.UserName = rp.User and ra.AcctStartTime = rp.date
where 
    rp.user = '$login'
    ";
    if( $date_from )
        $sql .= " and rp.date >= from_unixtime( $date_from ) ";
    if( $date_to )
        $sql .= " and rp.date <= from_unixtime( $date_to ) ";
    $sql .= " order by date desc limit 1000";

    $q = $db_radius->query( $sql );
    $r = array();
    while( $row = $db_radius->fetch_array( $q ) )
        $r[] = $row;
    
    var_set( 'report', $r );

    return true;
}

function get_skk_connects()
{
	global $db,$vars;
        
        $r1 = $db->query("set character set utf8");
	
	if($vars['date_from']['val'] != "") $sql = "
SELECT  t1.CODE, t1.USERCODE,t1.DATEDO,t1.OPIS, tbu.FIO as NEWKLIENT, 
        CONCAT(IFNULL(t2.ADR,''),', кв.',IFNULL(t1.APART,'')) as ADDRESS,
        CONCAT(IFNULL(tbu.TEL,''),' ',IFNULL(tbu.TELMOB,'')) as PHONE,
        CONCAT(IFNULL(tbc.OPIS,''),'; ',IFNULL(tbc.DATEADD,''),'; ',IFNULL(tbp.FIO,''),'; ',IFNULL(tbo.FIO,'')) as COMMENTS
  FROM userside.tbl_journal as t1, userside.tbl_house as t2, userside.tbl_journal_comments as tbc, userside.tbl_oper as tbo, userside.tbl_pers as tbp,
       userside.tbl_base as tbu, userside.tbl_conf_billing as tbb
  WHERE t1.`STATUS`=1 AND t1.TYPER=25  AND t1.HOUSECODE=t2.CODE AND tbc.JOURNALCODE=t1.CODE AND tbo.CODE=tbc.OPERCODE 
  AND tbo.PERSCODE=tbp.CODE AND tbu.CODE=t1.USERCODE AND tbu.BILLCODE=tbb.BILLCODE AND tbb.NAZV LIKE '".$vars['firm']['val']."' 
  AND t1.DATEMUST>= ".$vars['date_from']['val']."  limit 500";
	else $sql = "
SELECT  t1.CODE, t1.USERCODE,t1.DATEDO,t1.OPIS, tbu.FIO as NEWKLIENT, 
        CONCAT(IFNULL(t2.ADR,''),', кв.',IFNULL(t1.APART,'')) as ADDRESS,
        CONCAT(IFNULL(tbu.TEL,''),' ',IFNULL(tbu.TELMOB,'')) as PHONE,
        CONCAT(IFNULL(tbc.OPIS,''),'; ',IFNULL(tbc.DATEADD,''),'; ',IFNULL(tbp.FIO,''),'; ',IFNULL(tbo.FIO,'')) as COMMENTS
  FROM userside.tbl_journal as t1, userside.tbl_house as t2, userside.tbl_journal_comments as tbc, userside.tbl_oper as tbo, userside.tbl_pers as tbp,
       userside.tbl_base as tbu, userside.tbl_conf_billing as tbb
  WHERE t1.`STATUS`=1 AND t1.TYPER=25  AND t1.HOUSECODE=t2.CODE AND tbc.JOURNALCODE=t1.CODE AND tbo.CODE=tbc.OPERCODE 
  AND tbo.PERSCODE=tbp.CODE AND tbu.CODE=t1.USERCODE AND tbu.BILLCODE=tbb.BILLCODE AND tbb.NAZV LIKE '".$vars['firm']['val']."' limit 500";

	$r = array();
	$row = array();
	$q = $db->query($sql);
	$i=0;
	while($row = $db->fetch_array($q))  {
	      if (($i!=0) && ($r[$i-1]["CODE"]==$row["CODE"]))
	         $r[$i-1]["COMMENTS"] = $r[$i-1]["COMMENTS"]." | ".$row["COMMENTS"];
	      else {
	         $r[$i] = $row; 
	         $i++;
	        }
	      }
//	$r[$i] = array($i,$row["CODE"]);
	var_set('report', $r);
	
	return true;
}

function oktell_report()
    {
    global $db,$vars;

    if(!$vars['ident']['val']) return "Ident value is bad.";
    if(!$vars['date_from']['val']) return "date_from value is bad.";
    if(!$vars['date_to']['val']) return "date_to value is bad.";
    $ident = $vars['ident']['val'];
    $date_from = $vars['date_from']['val'];
    $date_to = $vars['date_to']['val'];
    

    $ab_number = isset($vars['telephone']['val']) ? '%'.$vars['telephone']['val'].'%' : '%'; 
    
    if (isset($vars['oper_name']['val']))
     {
        $oper_name = '%'.$vars['oper_name']['val'].'%';    
	$idoper = "xxxxxxxxxxxxxxxxxxxx111111111111";  //Произвольное значение присваивается чтоб в дальнейшем в запросе поиск шел только по маске oper_name
     }
     else {
        $oper_name = '%';    
	$idoper = "AB00000%";  //Идентификатор  IVR, чтоб выводились строки с IVR, идентификаторов которых нет в таблице операторов (IVR)
     }
    
    $oper_name = iconv("UTF-8","WINDOWS-1251", $oper_name); //преобразование кодировок
    $date_fr = date('Y-m-d H:i:s', $date_from); //из timestamp в строку с датой
    $date_to = date('Y-m-d H:i:s', $date_to);    

//    ini_set('mssql.charset', 'UTF-8');
	 $link = mssql_connect("111111\wwwLL", 'eweww', 'wewewe');

    if (!$link || !mssql_select_db('oktell_cc_temp', $link)) {
        var_set('report', 'Unable to connect or select database!!!');
        return true;
    }
    $sql = mssql_query("SELECT  valuestr FROM   [oktell_settings].[dbo].A_Settings WHERE name = 'usd~~~s_RecordedPath'");    
    $row = mssql_fetch_array($sql);  //получаем параметр в котором указан путь к файлам записей
    $rec_path = trim($row[0]); 
    if ( !(isset($rec_path) && !empty($rec_path)) ) 
	$rec_path = "C:\Program Files (x86)\oktell\Server\RecordedFiles\\"; // по умолчание
    mssql_free_result($sql);    

    $sql = mssql_query("SELECT DATEDIFF(mi, GETUTCDATE(), GETDATE())");    //вычисляем разницу timezone в минутах
    $row = mssql_fetch_array($sql); 
    $TZ_diff = $row[0];
    if ( !(isset($rec_path) && !empty($rec_path)) ) 
	$TZ_diff = 0;
    mssql_free_result($sql);    
    
    if ( isset($vars['oper_phone']['val']) && !empty($vars['oper_phone']['val'])  ) {
        $oper_phone = '%'.$vars['oper_phone']['val'].'%'; 
    
    $sql = mssql_query("SELECT op.name as oper, np.Prefix as opernum, eff.AbonentNumber, eff.DateTimeStart, DATEDIFF(SECOND,'1970-01-01', eff.DateTimeStart) as dtst,  eff.LenTime,  eff.IsRecorded,  eff.ALineNum, eff.BLineNum, pr.name as proj, ts.name as task, 0 as rec_link
 from A_Cube_CC_EffortConnections eff
 LEFT JOIN A_Cube_CC_Cat_Project pr ON (eff.IdProject=pr.id) 
 LEFT JOIN A_Cube_CC_Cat_Task ts  ON (eff.IdTask=ts.id) 
 LEFT JOIN A_Cube_CC_Cat_OperatorInfo op  ON (eff.IdOperator=op.id) 
 LEFT JOIN [oktell_settings].[dbo].A_RuleRecords rr ON (rr.ReactID = eff.IdOperator)
 LEFT JOIN [oktell_settings].[dbo].A_NumberPlanAction npa ON (rr.RuleID = npa.ExtraId)
 LEFT JOIN [oktell_settings].[dbo].A_NumberPlan np ON (npa.NumID = np.Id)
 WHERE     IdProject ='".$ident."'  and eff.LenTime>0 and DateTimeStart BETWEEN '".$date_fr."' and  '".$date_to."'
 and AbonentNumber LIKE '".$ab_number."' and (op.name like '".$oper_name."' or eff.IdOperator like '".$idoper."') and np.Prefix LIKE '".$oper_phone."'"); 
    }
    else 
       $sql = mssql_query("SELECT op.name as oper, np.Prefix as opernum, eff.AbonentNumber, eff.DateTimeStart, DATEDIFF(SECOND,'1970-01-01', eff.DateTimeStart) as dtst,  eff.LenTime,  eff.IsRecorded,  eff.ALineNum, eff.BLineNum, pr.name as proj, ts.name as task, 0 as rec_link
 from A_Cube_CC_EffortConnections eff
 LEFT JOIN A_Cube_CC_Cat_Project pr ON (eff.IdProject=pr.id) 
 LEFT JOIN A_Cube_CC_Cat_Task ts  ON (eff.IdTask=ts.id) 
 LEFT JOIN A_Cube_CC_Cat_OperatorInfo op  ON (eff.IdOperator=op.id) 
 LEFT JOIN [oktell_settings].[dbo].A_RuleRecords rr ON (rr.ReactID = eff.IdOperator)
 LEFT JOIN [oktell_settings].[dbo].A_NumberPlanAction npa ON (rr.RuleID = npa.ExtraId)
 LEFT JOIN [oktell_settings].[dbo].A_NumberPlan np ON (npa.NumID = np.Id)
 WHERE     IdProject ='".$ident."'  and eff.LenTime>0 and DateTimeStart BETWEEN '".$date_fr."' and  '".$date_to."'
 and AbonentNumber LIKE '".$ab_number."' and (op.name like '".$oper_name."' or eff.IdOperator like '".$idoper."') "); 
      
//    $sql = mssql_query("SELECT * FROM  A_Cube_CC_Cat_Project");    'eb1b5190-f2e0-447f-bc7a-296b26df223c'
    $row = mssql_fetch_array($sql);
    $i = 0;
    $r = array();
    $rep = array();
    while ($row = mssql_fetch_array($sql, MSSQL_ASSOC)) 
    {	
	$r[$i] = $row;
	$r[$i]['oper'] = iconv	("WINDOWS-1251","UTF-8", $row['oper']);
	$r[$i]['task'] = iconv("WINDOWS-1251","UTF-8", $row['task']);	
	$r[$i]['proj'] = iconv("WINDOWS-1251","UTF-8", $row['proj']);	
	
	$rep[$i]['Operator'] = $r[$i]['oper'];
	if (strlen($r[$i]['BLineNum'])>5) 
	{

	    $rep[$i]['Called'] = $r[$i]['opernum'];
    	    $rep[$i]['Caller'] = $r[$i]['AbonentNumber'];
    	}
    	else if ($r[$i]['BLineNum'] == "IVR")
    	 {
	    $rep[$i]['Called'] = "IVR";  
    	    $rep[$i]['Caller'] = $r[$i]['AbonentNumber'];
    	}
    	else 
    	 {
	    $rep[$i]['Caller'] = $r[$i]['opernum'];
    	    $rep[$i]['Called'] = $r[$i]['AbonentNumber'];
    	}
	
        $timest = $r[$i]['dtst']-$TZ_diff*60; //время в timestamp с учетом timezone
        $dt  = date('Y-m-d H:i:s',$timest); 
        $r[$i]['dtst'] = $dt;
        $rep[$i]['DateStart'] = $dt;
        $rep[$i]['LenTime'] = $r[$i]['LenTime'];
        $rep[$i]['rec_link'] = "";	    

	if ($r[$i]['IsRecorded'])  //если разговор записан, то формируем путь к файлу записи
	{
    	    $ms = substr($r[$i]['DateTimeStart'], strlen($r[$i]['DateTimeStart'])-5, 3);  //миллисекунды указанные в дате начала разговора
	    $dt2  = date('Hi',$timest); //часыминуты
	    $dt1  = date('Ymd',$timest); //годмесяцдень
	    $rec_link = $rec_path.$dt1."\\".$dt2."\\"."mix_".$r[$i]['ALineNum']."_".$r[$i]['BLineNum']."__".date('Y_m_d__H_i_s',$timest)."_$ms.wav";	
	    $r[$i]['rec_link'] = $rec_link; //сохраняем путь в массиве
	    $rep[$i]['rec_link'] = $rec_link; //сохраняем путь в массиве	    
	}
	$i++;
    }
    $r[0][0] = $date_fr;
//    $row[0][0] = iconv("cp936","UTF-8","3dcdf4d5-feff-4d62-ae61-009ab2c7d8aa");
    $row = $r[0];
    $row[2] = "TEST";          
    $row[3] = iconv("windows-1251", "utf-8", "3dcdf4d5-feff-4d62-ae61-009ab2c7d8aa");
    $id = $row[0] ;
    $name =$row[1] ;


/*    for ($i=4; $i < 10000; $i++) {
//               $row[$i] = "3dcdf4d5-feff-4d62-ae61-009ab2c7d8aa"; //iconv("cp".$i, "utf-8", "3dcdf4d5-feff-4d62-ae61-009ab2c7d8aa");
	        $row[$i]=iconv("UTF-8","cp".$i, $id);                                       
          }
*/
//    $row[0][0] = "3dcdf4d5-feff-4d62-ae61-009ab2c7d8aa";
    file_put_contents("/tmp/tttt.log", $row);
    mssql_free_result($sql);

    $name=iconv("WINDOWS-1251","UTF-8", $name);

//    $row[0] = iconv("Cyrillic_General_CI_AS","UTF-8", $id);                                       
    $row[1] = $name;

    mssql_close();
    var_set('report', $rep);

    return true;
}




function php_info()
{
    phpinfo();
    return true;
}

?>

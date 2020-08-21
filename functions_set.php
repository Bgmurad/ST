<?php
function set_password()
{
	global $vars;

	$ud = get_user_data_ids($vars['external_id']['val']);
	if(!is_array($ud)) return "Not found account with external_id=".$vars['external_id']['val'];

	$cmd = UTM_CMD."-a edit_user -user_id ".$ud['uid']." -password \"".$vars['password']['val']."\" 2>&1";
	my_exec($cmd);
	return true;
}

function set_user_status()
{
    global $db,$vars;
    
    
    # if(!$vars['is_blocked']['val']) return "New is_blocked value is bad.";
    
    # if ($vars['is_blocked']['val'] !=0 || $vars['is_blocked']['val'] !=1792) {"New is_blocked value is bad.";}
    if($vars['is_blocked']['val']=='0') { $int_status='1'; }
    elseif($vars['is_blocked']['val']=='1792') {$int_status='0'; }
    else return "New is_blocked value is bad.";
    
    $ud = get_user_data_ids($vars['external_id']['val']);
    if(!is_array($ud)) return "Not found account with external_id=".$vars['external_id']['val'];
    
    $account_id = $ud['account_id'];

    $q1 = $db->fetch_row_array("
    
    SELECT atl.account_id from account_tariff_link atl, credits c 
    WHERE atl.account_id=c.account_id 
    AND atl.tariff_id in (721,722,723) AND atl.is_deleted=0 AND c.is_passed=1
    AND atl.account_id=".$account_id."

");
    
    if(is_array($q1)) return "На тарифе -Весенний вклад- уже был активирован тестовый период";

    $cmd = UTM_CMD." -a change_account_block_status -account_id ".$ud['basic_account']." -is_blocked ".$vars['is_blocked']['val']."  -int_status ".$int_status." 2>&1";
    my_exec($cmd);
    #echo $cmd;
    return true;
}
                                                
function plan_user_block()
{
    global $vars;
    $block = 1792;
    $date_from = $vars['date_from']['val'];
    $date_to = $vars['date_to']['val'];

    if( $date_to <= $date_from )
        $date_to = 2000000000;
    
    $ud = get_user_data_ids($vars['external_id']['val']);
    if(!is_array($ud)) return "Not found account with external_id=".$vars['external_id']['val'];
    if( $date_from < time() ) return "date_from is in past";
                    
    $cmd = UTM_CMD." -a add_account_block -account_id ".$ud['basic_account']." -is_blocked $block -block_start_date $date_from -block_end_date $date_to 2>&1";
    my_exec($cmd);
    return true;
}
                                                

function set_user_data()
{
	global $vars;

	$ud = get_user_data_ids($vars['external_id']['val']);
	if(!is_array($ud)) return "Not found account with external_id=".$vars['external_id']['val'];

	$q = array();
	if($vars['full_name']['val'] != "") $q[] = "-full_name \"".$vars['full_name']['val']."\"";
	if($vars['is_juridical']['val'] != "") $q[] = "-is_juridical ".$vars['is_juridical']['val'];
	if($vars['password']['val'] != "") $q[] = "-password \"".$vars['password']['val']."\"";
	if($vars['work_telephone']['val'] != "") $q[] = "-work_tel \"".$vars['work_telephone']['val']."\"";
	if($vars['home_telephone']['val'] != "") $q[] = "-home_tel \"".$vars['home_telephone']['val']."\"";
	if($vars['mobile_telephone']['val'] != "") $q[] = "-mob_tel \"".$vars['mobile_telephone']['val']."\"";
	if($vars['email']['val'] != "") $q[] = "-email \"".$vars['email']['val']."\"";
	if($vars['passport']['val'] != "") $q[] = "-passport \"".$vars['passport']['val']."\"";
	if($vars['comment']['val'] != "") $q[] = "-comments \"".$vars['comment']['val']."\"";

	if(!count($q)) return "No data to change!";

	$cmd = UTM_CMD."-a edit_user -user_id ".$ud['uid']." ".implode(" ", $q)." 2>&1";
	my_exec($cmd);
	return true;
}
/*
function set_promised_payment()
{
	global $db,$vars;

	//if($vars['date_to']['val'] < time()) return "Date_to < current date.";

	$q = $db->query("SELECT lc.credit FROM lru_credit AS lc, accounts AS a WHERE a.external_id=".$vars['external_id']['val']." 
	AND a.is_deleted=0 AND lc.account_id=a.id AND lc.status=0");
	if($db->num_rows($q) > 0) return "Account already has a promised payment.";
	
	$r = $db->fetch_row_array("SELECT id FROM accounts WHERE external_id=".$vars['external_id']['val']." AND is_deleted=0");
	if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];
	$account_id = $r['id'];

	$cmd = UTM_CMD."-a edit_account -account_id ".$account_id." -credit ".$vars['promised_payment']['val']." 2>&1";
	my_exec($cmd);

	$db->query("INSERT INTO lru_credit SET account_id=".$account_id.", credit=".$vars['promised_payment']['val'].", ch_date=".$vars['date_to']['val']);
	return true;
}

*/
function set_promised_payment()
	
	{
	global $db,$vars;
	
	$r = $db->fetch_row_array("SELECT id FROM accounts WHERE external_id=".$vars['external_id']['val']." AND is_deleted=0");
	if(!is_array($r)) return "не найдел лицевой счет для ".$vars['external_id']['val'];
	$account_id = $r['id'];
    $firm_row = $db->fetch_row_array( "select uap.value firm from users u join user_additional_params uap on ( uap.userid = u.id and uap.paramid = 2 ) where u.basic_account = $account_id and u.is_deleted = 0" );
    $firm = $firm_row['firm'];
	
	$r = $db->fetch_row_array("SELECT balance,credit from accounts where id= ".$account_id." "); // and  credit=0 ");
	$balance = $r['balance'];
//	if($balance>0) return "на балансе есть средства";
	$credit = $r['credit'];
	    if ($credit > 1)
	    {
	    $q = $db->query("SELECT * from payment_transactions where account_id=".$account_id."
	    and method=7 and comments_for_admins='CREDIT CLOSED' and payment_enter_date > unix_timestamp( DATE_FORMAT(NOW() ,'%Y-%m-01') )
	    ");
	    if($db->num_rows($q)>0) return "кредит использован";
	    	
	    $q1 = $db->fetch_row_array("SELECT from_unixtime(burn_time) as ad from payment_transactions where 
	    account_id=".$account_id." and method=7 and comments_for_admins like 'CREDIT OPEN UNTIL%' ");
	    if(!is_array($q1)) return "бессрочно";
	    $b1=$q1['ad'];
	    $b="до $b1 ";
	    }
	    
	    else
	    {
	    
	    $q = $db->query("SELECT from_unixtime(payment_enter_date) as c 
	    from payment_transactions where account_id=".$account_id."
	    and method=7  and payment_enter_date > unix_timestamp( DATE_FORMAT(NOW() ,'%Y-%m-01') )   ");
	    if($db->num_rows($q)>0) return "Вы уже использовали обещанный платеж в этом месяце";
	    
	    $q1 = $db->query(" SELECT atl.account_id   from account_tariff_link atl, credits c  
	    WHERE atl.account_id=c.account_id  AND atl.tariff_id in (721,722,723) AND atl.is_deleted=0 
	    AND atl.account_id=".$account_id." ");
	    if($db->num_rows($q1)>0) return "На тарифе -Весенний вклад- Вы использовали обещанный платеж ранее";
	    	    		
	    //выбираем переодические стоимости
	    $sum1=0;
	    $r1 = $db->fetch_row_array("select ifnull(sum(psd.cost),0) as s from service_links sl, services_data sd, periodic_services_data psd where 
	    sl.service_id=sd.id and sd.id=psd.id and sd.is_deleted=0 and sl.is_deleted=0 and psd.is_deleted=0 and sl.account_id=".$account_id." ");
	    $sum1= $r1['s']; 
	    //выбираем разовые стоимости
	    $sum2=0;
	    $r2 = $db->fetch_row_array("select ifnull(sum(osd.cost)*3,0) as s
	    from once_service_data osd, tariffs_services_link tsl, account_tariff_link atl , services_data sd
	    where atl.tariff_id=tsl.tariff_id and tsl.service_id=osd.id and osd.id=sd.id and osd.is_deleted=0 and atl.is_deleted=0 and tsl.is_deleted=0 
	    and sd.link_by_default=0 and sd.service_type=1	and atl.account_id=".$account_id." ");
	    $sum2 = $r2['s'];
	    $sum = (($sum1+$sum2)*1.05)-$balance;
// округляем сумму кредита
	    $sum = ceil($sum);
	    if($balance > ($sum1+$sum2) ) return "На балансе достаточно средств";
//	    $sum=($sum1+$sum2-$balance)*1.05; 
//	if($balance > $sum)) return "На балансе есть средства $sum $balance";
	    //$burn_date=time()+259200;
        $cr_len = ( $firm == 'zzzzzzz' ? 3 : 7 ); // days
	      $burn_date=time() + $cr_len * 3600 * 24;
	
	    $cmd = UTM_CMD." -a add_credit_payment -account_id $account_id -payment $sum -currency_id 810 -burn_date $burn_date -payment_method 7 -turn_on_inet 1 2>&1 ";
		
		if ($vars['do']['val'] == '1' ) 
		{
		my_exec($cmd);
		$b="кредит успешно открыт на $cr_len суток на сумму $sum р.";
		}
		else
		{
		//$b ='';
		$b='команда set выполнена успешно';
		return true;
		}
	    }
	//return true;
	return  "$b";
	// "$b";
	}

function change_tariff()
{
	global $db,$vars;

	if(!$vars['new_tariff_id']['val']) return "New tariff_id value is bad.";
	if(!$vars['old_tariff_id']['val']) return "Old tariff_id value is bad.";
	if(!$vars['tlink_id']['val']) return "New tlink_id value is bad.";
	

        $ud = get_user_data_ids($vars['external_id']['val']);
        if(!is_array($ud)) return "Not found account with external_id=".$vars['external_id']['val'];
                
        
        $r = $db->fetch_row_array("SELECT id, discount_period_id FROM account_tariff_link WHERE account_id=".$ud['account_id']." AND tariff_id=".$vars['old_tariff_id']['val']." and id=".$vars['tlink_id']['val']." AND is_deleted=0");
	if(!is_array($r)) return "Old tariff_id value is wrong.";

	#$cmd = UTM_CMD."-a link_tariff -user_id ".$ud['uid']." -account_id ".$ud['basic_account']." -tariff_current ".$vars['old_tariff_id']['val']." -tariff_next ".$vars['tariff_id']['val']." -discount_period_id ".$r['discount_period_id']." -tariff_link_id ".$r['id']." 2>&1";
	$cmd = UTM_CMD."-a change_user_next_tariff -user_id ".$ud['uid']." -account_id ".$ud['basic_account']." -tariff_current ".$vars['old_tariff_id']['val']." -tariff_next ".$vars['new_tariff_id']['val']." -discount_period_id ".$r['discount_period_id']." -tariff_link_id ".$vars['tlink_id']['val']." 2>&1";
	my_exec($cmd);
	return true;
}

function change_tariff_instant()
{
	global $db,$vars;
    global $utm5_tariff_link_id;
    global $MA_utm_serv_links;

    $new_tariff_id = $vars['new_tariff_id']['val'];
    $tlink_id = $vars['tlink_id']['val'];
    $is_refund = $vars['is_refund']['val'];
    $ext_id = $vars['external_id']['val'];

    $ud = get_user_data_ids($ext_id);
    if(!is_array($ud)) return "Not found account with external_id=$ext_id";

	$dp = $db->fetch_row_array( "select id from discount_periods where is_expired=0 and static_id=1" );
	if( ! $dp ) return "Can't find actual discount period";
	$dp_id = $dp['id'];

    $account_id = $ud['account_id'];
    $user_id = $ud['uid'];
	$passwd = $ud['password'];

    $firm_row = $db->fetch_row_array( "select uap.value firm from users u join accounts a on ( a.id = u.basic_account and u.is_deleted = 0 and a.is_deleted = 0 ) join user_additional_params uap on ( uap.userid = u.id and uap.paramid = 2 ) where a.external_id = $ext_id" );
    $firm = $firm_row['firm'];

	$h = $db->fetch_row_array( "select house_id from users where id=\"" . $ud['uid'] . "\" limit 1" );
	$h_id = $h['house_id'];
                
    $r = $db->fetch_row_array("SELECT tariff_id FROM account_tariff_link WHERE account_id='$account_id' and id='$tlink_id' AND is_deleted=0");
	if(!is_array($r)) return "Wrong tlink_id.";
    $old_tariff_id = $r['tariff_id'];
    if( $old_tariff_id == $new_tariff_id )
        return "Same as old tariff";

    $comment = "";

    if( $is_refund )
    {
        $sql = "select name from tariffs where id = '$new_tariff_id'";
        $r = $db->fetch_row_array( $sql );
        $tariff_to = $r['name'];
        $sql = "
select
    t.id tariff_id
    , t.name tariff_name
    , sum(cost) cost
    , round( ceil( sum(cost)/( dp.end_date - dp.start_date ) * ( dp.end_date - unix_timestamp() ) * 100 ) / 100, 2 ) refund 
from 
    service_links sl 
    join periodic_services_data psd on psd.id = sl.service_id 
    join periodic_service_links psl on psl.id = sl.id 
    join discount_periods dp on dp.id = psl.discount_period_id 
    join account_tariff_link atl on atl.id = sl.tariff_link_id
    join tariffs t on t.id = atl.tariff_id
where 
    sl.is_deleted = 0 
    and psl.discounted_in_curr_period > 0 
    and sl.tariff_link_id = '$tlink_id'
        ";
        $r = $db->fetch_row_array( $sql );
        $cost = $r['cost'];
        $refund = $r['refund'];
        $tariff_from = $r['tariff_name'];
        $comment = "Перерасчёт по смене тарифа с $tariff_from на $tariff_to";
        $cmd = UTM_CMD." -a add_payment -account_id \"$account_id\" -payment \"$refund\" -currency_id 810 -payment_method 102 -turn_on_inet 0 -comment \"$comment\" -admin_comment \"$comment\" 2>&1";
        if( $refund > 0 )
            my_exec( $cmd );
    }

    $sql = "
select 
    sl.id
    , sl.service_id
    , sd.service_name
    , sd.parent_service_id
    , inet_ntoa( 0xffffffff & ig.ip ) ip
    , inet_ntoa( 0xffffffff & ig.mask ) mask
    , ig.uname
    , ig.upass
    , ig.mac
    , ig.allowed_cid ip_cid
    , tn.tel_number
    , tn.login tel_login
    , tn.password tel_pass
    , tn.allowed_cid tel_cid 
from 
    service_links sl 
    join services_data sd on sd.id = sl.service_id 
    left join iptraffic_service_links ipsl on ipsl.is_deleted = 0 and ipsl.id = sl.id 
    left join ip_groups ig on ig.is_deleted = 0 and ig.ip_group_id = ipsl.ip_group_id 
    left join tel_numbers tn on tn.is_deleted = 0 and tn.slink_id = sl.id 
where 
    sl.is_deleted = 0 
    and sl.tariff_link_id = $tlink_id
order by sl.id
    ";

    $linked_services = array();
    $comment = "";

    $q = $db->query( $sql );
    while( $r = $db->fetch_array( $q ) )
        $linked_services[] = $r;

    // Удаляем тариф
    $cmd = UTM_CMD." -a unlink_tariff -user_id $user_id -account_id $account_id -tariff_link_id $tlink_id 2>&1";
    my_exec( $cmd );

    // Добавляем тариф
    $cmd = UTM_CMD . "-a link_tariff -user_id $user_id -account_id $account_id  -discount_period_id $dp_id -tariff_current $new_tariff_id";

    $xml_parser = xml_parser_create( 'UTF-8' );
    xml_parser_set_option( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_set_element_handler( $xml_parser, "startElement", "endElement" );
	xml_set_character_data_handler( $xml_parser, "characterData" );
    error_log( "Executing '$cmd'" );
    if( ! ( $fp = popen( "$cmd 2>/dev/null", "r" ) ) ) return( "could not open XML input" );

    while( $data = fread( $fp, 4096 ) )
    {
        $rows = preg_split( "/\n/", $data );
        if( is_array( $rows ) )
            foreach( $rows as $row )
                error_log( $row );
        else
            error_log( $data );
            if( ! xml_parse( $xml_parser, $data, feof( $fp ) ) ) 
        return ( sprintf( "XML error: %s at line %d"
            , xml_error_string( xml_get_error_code( $xml_parser ) )
            , xml_get_current_line_number( $xml_parser ) ) );
    }
    xml_parser_free( $xml_parser );
    pclose( $fp );

    if( ! $utm5_tariff_link_id ) return "Ошибка выполнения команды добавления нового тарифа.";

    $old_sl_id = 0;
    $new_sl_id = 0;
    if( is_array( $linked_services ) )
        foreach( $linked_services as $service )
        {
            if( $old_sl_id != $service[ 'id' ] )
                $new_sl_id = 0;
            $old_sl_id = $service[ 'id' ];
            $old_service_id = $service[ 'service_id' ];
            $parent_id = $service[ 'parent_service_id' ];
            $service_row = $db->fetch_row_array( "select sd.id, sd.service_name from services_data sd where tariff_id = $new_tariff_id and parent_service_id = $parent_id" );
            $service_id = $service_row[ 'id' ] + 0;
            $service_name = $service_row[ 'service_name' ];
            if( $service_id )
            {
                $c1 = $c2 = "";
                if( $service[ 'ip' ] )
                    $c1 = "-iptraffic_login \"" . $service[ 'uname' ] . "\" -iptraffic_password \"" . $service[ 'upass' ] . "\" -dont_use_fw 0 -ip_address \"" . $service[ 'ip' ] . "\" -mac \"" . $service[ 'mac' ] . "\" -iptraffic_allowed_cid \"" . $service[ 'ip_cid' ] . "\"";

                if( $service[ 'tel_number' ] )
                    $c2 = "-tel_number \"" . $service[ 'tel_number' ] . "\" -tel_login \"" . $service[ 'tel_login' ] . "\" -tel_password \"" . $service[ 'tel_pass' ] . "\" -tel_allowed_cid \"" . $service[ 'tel_cid' ] . "\"";

                $cmd = UTM_CMD . " -a link_service -user_id $user_id -account_id $account_id -service_id $service_id -discount_period_id $dp_id -tariff_link_id $utm5_tariff_link_id -unabon 1 -unprepay 0 -slink_id $new_sl_id $c1 $c2 2>&1";
                my_exec( $cmd );
                sleep( 2 );
		
		
		/*$url = "https://xxxxxxx.ru/dataserver/query.php?cmd=add_user_smotreshka&external_id=$external_id";
		$cmd="GET '".$url."' > /dev/null";
		my_exec( $cmd );
		
		$url = "https://xxxxxxx.ru/dataserver/query.php?cmd=sinc_user_smotreshka&external_id=$external_id";
		$cmd="GET '".$url."' > /dev/null";
		my_exec( $cmd );
		*/
		
		
                $slink_row = $db->fetch_row_array( "select max(id) id from service_links where is_deleted = 0 and account_id = $account_id and service_id = $service_id and tariff_link_id = $utm5_tariff_link_id" );
                if( is_array( $slink_row ) )
                {
                    $new_sl_id = $slink_row[ 'id' ] + 0;
                    if( $service[ 'ip' ] )
                    {
                        $port_link_id = ldev_get_ip_link_data( $service[ 'ip' ] );
                        $port_link_id = $port_link_id[ 'id' ];
                        error_log( "Rebinding $port_link_id " . `sudo /usr/local/scripts/snmp_mgmt/rebind_port_rate.sh $port_link_id` );
                    }
                }
                else
                    $comment .= "\nОшибка при выполнении команды добавления услуги \"$service_name\" id $service_id.";
            }
            else
            {
                $comment .= "\nНе найдено совместимой услуги для услуги \"" . $service[ 'service_name' ] . "\" id " . $service[ 'id' ] . ". Услуга не перенесена.";

                if( $service[ 'ip' ] )
                {
                    // Отвязываем IP и удаляем STB
                    global $MA_url, $MA_key, $MA_net_id;
                    $url = $MA_url;
                    $key = $MA_key;
                    $net_id = $MA_net_id;

                    $ip = $service['ip'];
                    $stb_id = $service['mac'];

                    if( $stb_id + 0 > $net_id + 0 )
                    {
                        MA_del_stb( $url, $net_id, $key, $ext_id, $stb_id );
                        $comment .= "\nУстройство STB_ID $stb_id удалено.";
                    }

                    $sql = "SELECT lnk.id, lnk.ldev_id, lnk.port_id, lnk.port_access_id, lgk.ip ldev_ip
                            FROM tt.tlc_nobj_links lnk
                                 join tt.tlc_nobj_logical lgk using( ldev_id )
                            WHERE port_ip='$ip'";
                    $rr = $db->fetch_row_array( $sql );
                    $ldev_id = $rr['ldev_id']+0;
                    $port_id = $rr['port_id']+0;
                    $link_id = $rr['id']+0;
                    $links_count = $db->fetch_row_array( "SELECT COUNT(id) c FROM tt.tlc_nobj_links WHERE ldev_id = $ldev_id AND port_id = $port_id AND port_link_id != -1" );
                    if ($links_count['c'] > 1)
                       $db->query( "DELETE FROM tt.tlc_nobj_links WHERE id = '$link_id'" );
                    elseif( $links_count['c'] == 1 )
                        $db->query( "update tt.tlc_nobj_links set port_link_id = -1, port_access_id = -1, port_ip = -1, port_username = NULL, port_vlan_id = -1, port_provider_id = -1, last_update = unix_timestamp(), status = 0 WHERE id = '$link_id'" );
                    if( $links_count['c'] > 0 ) 
                        $db->query( "UPDATE tt.zz_dhcpservers SET ds_update_req=1 WHERE ds_update_req=0" );
                }

                if( $parent_id == 814 ) // Если DrWeb
                {
                    function httpsPost($Url, $strRequest)
                    {
                        $ch=curl_init();
                        curl_setopt($ch, CURLOPT_URL, $Url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_POST, 1) ;
                        curl_setopt($ch, CURLOPT_USERPWD, "xxxxx:xxxxxxxxxxx");
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $strRequest);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                        $result = curl_exec($ch);
                        curl_close($ch);
                        return $result;
                    }

                    $expires1 = time() - 3 * 3600;
                    $expires = date( "YmdHis", $expires1 );

                    $r = $db->fetch_row_array("SELECT add_date as d FROM drweb_data WHERE slink_id=$old_sl_id");
                    if( is_array( $r ) )
                    {
                        $startdate = $r['d'];

                        $url ="http://xxxxxxx.ru:9080/api/3.0/stations/change.ds?id=${ext_id}_${startdate}&expires=$expires";
                        $db->query( "update drweb_data set expire_date=$expires1, is_deleted=1 where external_id=$ext_id and add_date=$startdate" );

                        error_log( "Executing '$url'" );
                        $Response = httpsPost( $url, '' );
                        error_log( "Result: '$Response'" );
                    }
                }

            }
        }

    // Ищем услуги, добавляемые в новом тарифе по умолчанию, но по каким-то причинам не добавившиеся после смены тарифа
    // и добавляем их
    $sql = "
select 
    sd.id
    , sd.service_type 
    , sd.parent_service_id
from 
    account_tariff_link atl 
    join services_data sd on sd.tariff_id = atl.tariff_id and sd.is_deleted = 0 and sd.link_by_default = 1 and sd.service_type in (2,3)
where 
    atl.id = $utm5_tariff_link_id 
    and sd.id not in ( select service_id from service_links where tariff_link_id = $utm5_tariff_link_id and is_deleted = 0 )
    ";
    $q = $db->query( $sql );
    while( $r = $db->fetch_array( $q ) )
    {
        $service_id = $r['id'];
        $psid = $r['parent_service_id'];
        $stb_id = 0;
        $stb_pass = 0;
        $stb_type = 0;
        if( $MA_utm_serv_links[ $service_id ] ) // Если услуга ТВ - создаем
        {
            global $MA_url, $MA_key, $MA_net_id;
            $url = $MA_url;
            $key = $MA_key;
            $net_id = $MA_net_id;

            $u_data = MA_get_user_data( $ext_id );

            preg_match( "/(\S+)\s+(\S+)\s*(.*)/", trim( $u_data['fn'] ), $u_name );
            $ln = trim( $u_name[1] );
            $fn = trim( $u_name[2] );
            $mn = trim( $u_name[3] );
            if( ! $mn ) $mn = "нет";

            $ad = $u_data['ad'];
            $ps = $u_data['ps']; if( ! $ps ) $ps = "Неизвестно";
            $ph = $u_data['p1'] . ", " . $u_data['p2'] . ", " . $u_data['p3'];
            $res = MA_add_user( $url, $net_id, $key, $ext_id, $ln, $fn, $mn, $ad, $ps, $ph );

            $stb_id = $net_id . $u_data['id'] . rand( 0, 9 );
            $stb_pass = MA_gen_pass( 8 );
            $stb_type = 'NV101';
            $res = MA_add_stb( $url, $net_id, $key, $ext_id, $stb_id, $stb_pass, $stb_type );
            if( ! ( is_int( $res ) && $res == 0 ) )
            {
                $stb_id = 0;
                $stb_pass = 0;
                $stb_type = 0;
            }

            $res = MA_add_pack( $url, $net_id, $key, $ext_id, $stb_id, $MA_utm_serv_links[ $service_id ] );
            if( $u_data['int_status'] == 1 )
                $res = MA_on_user( $url, $net_id, $key, $ext_id );
            $comment .= "\nДобавлена STB_ID $stb_id STB_PASS $stb_pass.";
        }
        if( $r['service_type'] == 3 ) // Если услуга ip трафика добавляем к команде ip
        {
            $ip = get_free_ip( $h_id, $firm );
            if( ! $ip ) return "Can't find ip for house_id $h_id";
            if( $psid == 614 ) // Если это IPtv 
                $l1 = "tv$ext_id-" . rand( 10, 99 );
            elseif( $psid == 756 ) // Если это Телефония 
                $l1 = "pv$ext_id-" . rand( 10, 99 );
            else
                $l1 = $ext_id . "-" . rand( 10, 99 );
            $c1 = "-iptraffic_login \"$l1\" -iptraffic_password \"$passwd\" -dont_use_fw 0 -unprepay 0 -ip_address \"$ip\"" . ( ( $stb_id && ( $psid == 614 ) ) ? " -mac $stb_id " : "" );
        }
        $cmd = UTM_CMD . " -a link_service -user_id \"" . $ud['uid'] . "\" -account_id \"$account_id\" -service_id \"$service_id\" -unabon 1 -discount_period_id \"$dp_id\" -tariff_link_id $utm5_tariff_link_id $c1 2>&1";
        my_exec( $cmd );

		if( $psid == 814 ) // Если drweb - создаем
		{ // Код спизжен из функции link_service
			// начинаем обрабатывать запрос для Дрвэб

			function httpsPost($Url, $strRequest)
			{
				$ch=curl_init();
				curl_setopt($ch, CURLOPT_URL, $Url);
				// Return a variable instead of posting it directly
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				// Active the POST method
				curl_setopt($ch, CURLOPT_POST, 1) ;
				curl_setopt($ch, CURLOPT_USERPWD, "xxxxx:xxxxxxxxxxx");

				curl_setopt($ch, CURLOPT_POSTFIELDS, $strRequest);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				// execute the connexion
				$result = curl_exec($ch);
				curl_close($ch);
				return $result;
			}
			if( $service_id == 815 ) // Тариф "Все подключено"
				$rate='2888b7ff-3625-465e-bcb8-957de17f6458';
			else // По дефолту подключаем Классик (69 р.)
				$rate='2888b7ff-3625-465e-bcb8-957de17f6458';

			//генерируем дату для  slink и для ссылки (для связи по ex_id s_id $startdate)
			$timestamp = time();
			$date_time_array = getdate($timestamp);
			$timestamp = mktime($date_time_array['hours'],$date_time_array['minutes'],$date_time_array['seconds'],
			$date_time_array['mon'],$date_time_array['mday'],$date_time_array['year']  );
			$h=$date_time_array['hours']; $m=$date_time_array['minutes']; $s=$date_time_array['seconds'];
			$M=$date_time_array['mon'];$D=$date_time_array['mday'];$Y=$date_time_array['year'];

			$startdate1=$timestamp;
			$startdate2=("$Y$M$D$h$m$s");

			$external_id=$ext_id;
			$pass='eee123';
			$expires=0;
			$link="http://xxx.xxx/download/download.ds?id=".$external_id."_".$startdate1." ";
			$url ="http://xxxxxxx.ru:908/api/3.0/stations/add.ds?id=".$external_id."_".$startdate1."&name=".$external_id."&password=".$pass."&rate=".$rate."&expires=".$expires." ";

			$db->query("insert into drweb_data values ('',".$external_id.",'".$pass."','".$account_id."','".$user_id."','".$service_id."','".$rate."','".$link."','".$startdate1."',0,0) ");

            error_log( "Executing '$url'" );
			$Response = httpsPost( $url, '' );
            error_log( "Result: '$Response'" );
			//закончили дрвэб    
        }
    }

    var_set( 'cost', $cost );
    var_set( 'refund', $refund );
    var_set( 'comment', $comment );
	return true;
}

function link_service()
{
    global $db,$vars;

    if(!$vars['service_id']['val']) return "Service value is bad.";
	if($vars['is_planed']['val'] =='0')
	{
	    $std='';
	} 
	else 
	{
	    $d = $db->fetch_row_array("select dp.end_date+3600 as start_date from discount_periods dp where dp.is_expired=0 and dp.static_id=1");
	    $std=" -start_date ".$d['start_date']." -discount_date ".$d['start_date'];
 	}
      
    //if(!$vars['service_start_date']['val']) return "Service value is bad.";

#echo " $std ";

    $ud = get_user_data_ids($vars['external_id']['val']);
    if(!is_array($ud)) return "Not found account with external_id=".$vars['external_id']['val'];

    $r = $db->fetch_row_array("select id from discount_periods where is_expired=0 and static_id=1");
    if(!is_array($r)) return "discount_peiod value is wrong.";

    if ( ( $vars['service_id']['val'] >773  and $vars['service_id']['val']<778 ) or $vars['service_id']['val'] == 815 or $vars['service_id']['val'] == 1148)
    {

// начинаем обрабатывать запрос для Дрвэб

function httpsPost($Url, $strRequest)
{
// Initialisation
$ch=curl_init();
// Set parameters
curl_setopt($ch, CURLOPT_URL, $Url);
// Return a variable instead of posting it directly
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// Active the POST method
curl_setopt($ch, CURLOPT_POST, 1) ;
//auth
curl_setopt($ch, CURLOPT_USERPWD, "zxxxx:xxxxxxxxxxx");
// Request
curl_setopt($ch, CURLOPT_POSTFIELDS, $strRequest);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
// execute the connexion
$result = curl_exec($ch);
// Close it
curl_close($ch);
return $result;
}
$service_id=$vars['service_id']['val'];
#switch($service_id)
#{
#case 774:$rate='91644cc3-1dc1-42dc-a41e-5ea001f5538d';
#case 775:$rate='ebe76ffc-69e1-4757-b2b3-41506832bc9b';
#case 776:$rate='2888b7ff-3625-465e-bcb8-957de17f6458';
#case 777:$rate='01fe9e60-6570-11de-b827-0002a5d5c51b';
#}

//премиум
if ($service_id == 774) {$rate='91644cc3-1dc1-42dc-a41e-5ea001f5538d';}
//премиум лето 2014
if ($service_id == 1148) {$rate='91644cc3-1dc1-42dc-a41e-5ea001f5538d';}
//стандарт
if ($service_id == 775) {$rate='ebe76ffc-69e1-4757-b2b3-41506832bc9b';}
//классик
if ($service_id == 776) {$rate='2888b7ff-3625-465e-bcb8-957de17f6458';}
// премиум сервер
if ($service_id == 777) {$rate='01fe9e60-6570-11de-b827-0002a5d5c51b';}
//классик для Все включено
if ($service_id == 815) {$rate='2888b7ff-3625-465e-bcb8-957de17f6458';}

//генерируем дату для  slink и для ссылки (для связи по ex_id s_id $startdate)
$timestamp = time();
$date_time_array = getdate($timestamp);
$timestamp = mktime($date_time_array['hours'],$date_time_array['minutes'],$date_time_array['seconds'],
$date_time_array['mon'],$date_time_array['mday'],$date_time_array['year']  );
$h=$date_time_array['hours']; $m=$date_time_array['minutes']; $s=$date_time_array['seconds'];
$M=$date_time_array['mon'];$D=$date_time_array['mday'];$Y=$date_time_array['year'];

//$date=("$Y-$D-$M $h:$m:$s");

$startdate1=$timestamp;
$startdate2=("$Y$M$D$h$m$s");

$service_id=$vars['service_id']['val'];
$external_id=$vars['external_id']['val'];
$account_id=$ud['basic_account'];
$user_id=$ud['uid'];
$pass='123';
$expires=0;
#if ($command eq 'add')
#    {//заведение
$cmd = UTM_CMD." -a link_service -user_id ".$ud['uid']." -account_id ".$ud['basic_account']." -service_id ".$vars['service_id']['val']." -discount_period_id ".$r['id']." -start_date ".$startdate1." -unabon 1 2>&1";
my_exec($cmd);
#echo $cmd;

$link="http://xxx.xxx./download/download.ds?id=".$external_id."_".$startdate1." ";
$url ="http://xxxxxxx.ru:908/api/3.0/stations/add.ds?id=".$external_id."_".$startdate1."&name=".$external_id."&password=".$pass."&rate=".$rate."&expires=".$expires." ";

//$db->query("insert into drweb_data values ('',".$external_id.",'".$pass."','".$account_id."','".$user_id."','".$service_id."','".$rate."','".$link."','".$startdate1."',0,0) ");

$s = $db->fetch_row_array("select max(sl.id) as slink_id from service_links sl where account_id=".$account_id." and service_id=".$service_id." ");
$slink_id=$s['slink_id'];
//echo $sql;
//echo $slink_id;
$db->query("insert into drweb_data values ('',".$external_id.",'".$pass."','".$account_id."','".$user_id."','".$service_id."','".$rate."','".$link."','".$startdate1."',0,0,'".$slink_id."') ");
//echo $db;



            error_log( "Executing '$url'" );
            $Response = httpsPost($url, '' );
            error_log( "Response: '$Response'" );

//закончили дрвэб    

    
    }
    else
    {
    $cmd = UTM_CMD." -a link_service -user_id ".$ud['uid']." -account_id ".$ud['basic_account']."  -service_id ".$vars['service_id']['val']." -discount_period_id ".$r['id']." ".$std." -unabon 1  2>&1";
    my_exec($cmd);
    }
    
    
    // тут будет запуск добавления или проверки смотрешки
    // тут будет запуск синхронизации пакетов смотрешки
    
//echo $cmd;
    return true;
}



function unlink_service()
{
    global $db,$vars, $MA_utm_serv_links;
    
    if(!$vars['service_id']['val']) return "Service value is bad.";
    if(!$vars['slink_id']['val']) return "slink_id value is bad.";
    
    if($vars['is_planed']['val']=='1') { $is_planed='1'; }
    elseif($vars['is_planed']['val']=='0') {$is_planed='0'; }
    else return "is_planed value is bad.";    
    
//    if($vars['is_planed']['val'] = '') return "is_planed value is bad.";
//    $is_planed=$vars['is_planed']['val'];    
    $ud = get_user_data_ids($vars['external_id']['val']);
    if(!is_array($ud)) return "Not found account with external_id=".$vars['external_id']['val'];
    $r = $db->fetch_row_array("select sl.id, sd.tariff_id, sd.link_by_default from service_links sl join services_data sd on sd.id = sl.service_id and sd.is_deleted = 0 where sl.account_id=".$ud['basic_account']." and sl.service_id=".$vars['service_id']['val']."
    and sl.id=".$vars['slink_id']['val']." and sl.is_deleted=0");
    if(!is_array($r)) return "slink_id value is wrong.";
    if( $r['tariff_id'] > 0 && $r['link_by_default'] == 1 ) return "can't delete default service in tariff";
    $is_refund = $vars['is_refund']['val'] == 1 ? 1 : 0;

    $cost = 0;
    $refund = 0;
    
    function httpsPost($Url, $strRequest)
    {
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_URL, $Url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1) ;
        curl_setopt($ch, CURLOPT_USERPWD, "zxxxx0xxxxxxxxxxx");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $strRequest);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    if($is_planed == 1 ) 
    {
        if ( ($vars['service_id']['val'] > 773  and $vars['service_id']['val'] < 778 ) or $vars['service_id']['val'] == 815 or $vars['service_id']['val'] == 1148 )
        {
            //начинаем с дрвэб
            $service_id=$vars['service_id']['val'];

            if ($service_id == 774) {$rate='91644cc3-1dc1-42dc-a41e-5ea001f5538d';}
            if ($service_id == 1148) {$rate='91644cc3-1dc1-42dc-a41e-5ea001f5538d';}
            if ($service_id == 775) {$rate='ebe76ffc-69e1-4757-b2b3-41506832bc9b';}
            if ($service_id == 776) {$rate='2888b7ff-3625-465e-bcb8-957de17f6458';}
            if ($service_id == 777) {$rate='01fe9e60-6570-11de-b827-0002a5d5c51b';}

            $sel = $db->fetch_row_array("select left(from_unixtime(dp.end_date - 4 * 3600 - 1)+0,14) as end_date,
            dp.end_date - 3601 as end_date1
            from periodic_service_links psl,
            discount_periods dp where psl.id=".$vars['slink_id']['val']." and psl.discount_period_id=dp.id ");

            $expires=$sel['end_date'];
            $expires1=$sel['end_date1'];
            $service_id=$vars['service_id']['val'];
            $external_id=$vars['external_id']['val'];
            $account_id=$ud['basic_account'];
            $user_id=$ud['uid'];

            $r = $db->fetch_row_array("SELECT discount_period_id FROM periodic_service_links WHERE id=".$vars['slink_id']['val']);
            if(!is_array($r)) return "Not found discount period for slink_id=".$vars['slink_id']['val'];
            $discount_period_id = $r['discount_period_id'];
            $r = $db->fetch_row_array("SELECT add_date as d FROM drweb_data WHERE slink_id=".$vars['slink_id']['val']);
            if(!is_array($r)) return "Not found startdate for slink_id=".$vars['slink_id']['val'];
            $startdate = $r['d'];

            $url ="http://drweb.xxxxxxx.ru:9080/api/3.0/stations/change.ds?id=".$external_id."_".$startdate."&expires=".$expires."";
            $db->query("update drweb_data set expire_date=".$expires1.", is_deleted=1 where external_id=".$external_id." and slink_id=".$vars['slink_id']['val']);

            $slink_id=$vars['slink_id']['val'];

            $cmd = UTM_CMD."-a unlink_service -slink_id ".$slink_id." 2>&1";
            my_exec( $cmd );

            $cmd = UTM_CMD . " -a edit_account -account_id ".$account_id." -unlimited 1 2>&1";
            my_exec( $cmd );
            sleep (2);

            $cmd = UTM_CMD . " -a link_service -user_id ".$user_id." -account_id ".$account_id." -service_id ".$service_id." -discount_period_id ".$discount_period_id." -expire_date $expires1 -unabon 1 2>&1";
            my_exec( $cmd );
            sleep (2);

            $cmd = UTM_CMD . " -a edit_account -account_id ".$account_id." -unlimited 0 2>&1";
            my_exec( $cmd );

            $s = $db->fetch_row_array("select max(sl.id) as new_slink_id from service_links sl where account_id=".$account_id." and service_id=".$service_id." ");
            $new_slink_id=$s['new_slink_id'];
            $db->query("update drweb_data set slink_id = ".$new_slink_id." where slink_id=".$slink_id." ");

            error_log( "Executing '$url'" );
            $Response = httpsPost($url, '' );
            error_log( "Response: '$Response'" );
        }
        else
        {
            
            $sel = $db->fetch_row_array("select left(from_unixtime(dp.end_date - 4 * 3600 - 1)+0,14) as end_date,
            dp.end_date - 3601 as end_date1, from_unixtime(dp.end_date - 4 * 3600) as end_date2,  sd.service_name, dp.id as discount_period_id, sl.tariff_link_id
            from periodic_service_links psl, discount_periods dp, service_links sl, services_data sd
            where psl.id=sl.id and sl.service_id=sd.id 
            and psl.id=".$vars['slink_id']['val']." and psl.discount_period_id=dp.id ");
            
            $expires=$sel['end_date'];
            $expires1=$sel['end_date1'];
            $expires2=$sel['end_date2'];
            $service_id=$vars['service_id']['val'];
            $service_name=$sel['service_name'];
            $external_id=$vars['external_id']['val'];
            $account_id=$ud['basic_account'];
            $user_id=$ud['uid'];
            $slink_id=$vars['slink_id']['val'];
            $discount_period_id = $sel['discount_period_id'];
            $tariff_link_id = $sel['tariff_link_id'];
            
            
            $cmd = UTM_CMD."-a unlink_service -slink_id ".$slink_id." 2>&1";
            my_exec( $cmd );
            
            $cmd = UTM_CMD . " -a edit_account -account_id ".$account_id." -unlimited 1 2>&1";
            my_exec( $cmd );
            sleep (2);
            
            $cmd = UTM_CMD . " -a link_service -user_id ".$user_id." -account_id ".$account_id." -service_id ".$service_id." -discount_period_id ".$discount_period_id." -expire_date $expires1 " . ( $tariff_link_id ? " -tariff_link_id $tariff_link_id " : "" ) . " -unabon 1 2>&1";
            my_exec( $cmd );
            sleep (2);
            
            $cmd = UTM_CMD . " -a edit_account -account_id ".$account_id." -unlimited 0 2>&1";
            my_exec( $cmd );
            
            if ($service_id ==768 || $service_id ==1126 ||$service_id ==1084) 
                $mes='Необходимо отвязать IP адрес на оборудовании и удалить из интернет-тарифа в биллинге'; 
            
            $comment="Отключить услугу - Название услуги ".$service_name.",  ID услуги ".$service_id.", ID привязки ".$vars['slink_id']['val'].", дата ".$expires2." , </br>*удаление услуги уже запланированно в биллинге</br> ".$mes."  "; 
            $url = "https://xxxxxxx.ru/dataserver/query.php?cmd=new_request&external_id=";
            $url .= "$external_id&request_type=2&request_comment=$comment";
            $cmd="GET '".$url."' > /dev/null";
            my_exec( $cmd );
        }
    }
    else
    {    
        $account_id = $ud['basic_account'];
        $external_id = $vars['external_id']['val'];
        $slink_id = $vars['slink_id']['val'];
        $service_id = $vars['service_id']['val'];
        if( $is_refund )
        {
            $sql = "
select 
sl.service_id service_id
, sd.service_name service_name
, psd.cost cost
, round( ceil( psd.cost / ( dp.end_date - dp.start_date ) * ( dp.end_date - unix_timestamp( curdate() ) ) * 100 ) / 100, 2 ) refund 
from 
service_links sl 
join periodic_service_links psl on psl.id = sl.id and psl.is_deleted = 0 
join periodic_services_data psd on psd.id = sl.service_id 
join discount_periods dp on dp.id = psl.discount_period_id 
join services_data sd on sd.id = sl.service_id
where 
sl.is_deleted = 0 
and psl.discounted_in_curr_period > 0
and sl.id = '$slink_id'";
            $r = $db->fetch_row_array( $sql );
            $cost = $r['cost'];
            $refund = $r['refund'];
            $service_name = $r['service_name'];
            $comment = "Перерасчёт по отключению услуги $service_name";
            $cmd = UTM_CMD." -a add_payment -account_id \"$account_id\" -payment \"$refund\" -currency_id 810 -payment_method 102 -turn_on_inet 0 -comment \"$comment\" -admin_comment \"$comment\" 2>&1";
            if( $refund > 0 )
                my_exec( $cmd );
        }
        // Если удаляется услуга ТВ - удаляем пакет в МА
        if( $MA_utm_serv_links[ $service_id ] )
        {
            global $MA_url, $MA_key, $MA_net_id;
            $url = $MA_url;
            $key = $MA_key;
            $net_id = $MA_net_id;
            // Ищем приставки, привязанные к тому же тарифу, что и удаляемая услуга
            $sql = "
select 
mac stb_id 
from 
service_links sl 
join service_links sl2 on sl2.tariff_link_id = sl.tariff_link_id and sl2.is_deleted = 0 
join iptraffic_service_links ipsl on ipsl.id = sl2.id and ipsl.is_deleted = 0 
join ip_groups ig on ig.ip_group_id = ipsl.ip_group_id and ig.is_deleted = 0 and ig.mac > '' 
where 
sl.is_deleted = 0 
and sl.tariff_link_id > 0 
and sl.id = '$slink_id' 
group by stb_id";
            $q = $db->query( $sql );
            // Удаляем пакеты в МА для каждой найденной приставки
            while( $r = $db->fetch_array( $q ) )
                MA_del_pack( $url, $net_id, $key, $external_id, $r['stb_id'], $MA_utm_serv_links[ $service_id ] );
        }
        if ( ($vars['service_id']['val'] > 773  and $vars['service_id']['val'] < 778 ) or $vars['service_id']['val'] == 815 or $vars['service_id']['val'] == 1148 )
        {
            $expires1 = time() - 3 * 3600;
            $expires = date( "YmdHis", $expires1 );

            $r = $db->fetch_row_array("SELECT add_date as d FROM drweb_data WHERE slink_id=$slink_id");
            if( is_array( $r ) )
            {
                $startdate = $r['d'];

                $url ="http://xxxxxxx.ru:908/api/3.0/stations/change.ds?id=${external_id}_${startdate}&expires=$expires";
                $db->query( "update drweb_data set expire_date=$expires1, is_deleted=1 where external_id=$external_id and add_date=$startdate" );

                error_log( "Executing '$url'" );
                $Response = httpsPost($url, '' );
                error_log( "Response: '$Response'" );
            }
        }
        $cmd = UTM_CMD."-a unlink_service -slink_id ".$vars['slink_id']['val']." 2>&1";
        my_exec($cmd);
    }
    var_set( 'cost', $cost );
    var_set( 'refund', $refund );
	return true;
}
    
function activate_card()
{
	global $db,$vars;

	$r = $db->fetch_row_array("SELECT id FROM accounts WHERE external_id=".$vars['external_id']['val']." AND is_deleted=0");
	if(!is_array($r)) return "Not found account with external_id=".$vars['external_id']['val'];
	$account_id = $r['id'];

	$q = $db->query("SELECT * FROM card_info WHERE id=".$vars['card']['val']." AND secret='".$vars['secret']['val']."' AND expiration >unix_timestamp( NOW())");
	if(!$db->num_rows($q)) return "Карты с такими параметрами не существует, проверьте правильность введенных данных.";
	$r = $db->fetch_array($q);
	if($r['is_used']) return "Card is used.";
	if($r['is_blocked']) return "Card is blocked.";

    # Запрет использования карт на тарифе Половинка
    $sql = "select id from account_tariff_link where account_id = $account_id and tariff_id = 718 and is_deleted = 0";
    $q = $db->query( $sql );
	if($db->num_rows($q)) return "На Вашем тарифном плане запрещено пользоваться картами";
	
	$sql3 = "select 1  from payment_transactions where method=3 and account_id=".$account_id."";
	//echo $sql3;
	$q3 = $db->query($sql3);
	if($db->num_rows($q3)) return "".$vars['external_id']['val']." уже активировал карты ранее(в недавнее время).";
	
	
	//ищем активированные карты у абонента
	$sql1 = "select table_name from archives where table_type=7";
	$q1 = $db->query($sql1);
	while($t1 = $db->fetch_array($q1))
	{
	    $table_name=$t1['table_name'];
	    $sql2 = "select 1  from ".$table_name." where method=3 and account_id=".$account_id."";
	    //echo $sql2;
	    $q2 = $db->query($sql2);
	    if($db->num_rows($q2)) return "".$vars['external_id']['val']." уже активировал карты ранее."; 
	    
	    	
	}
	
	
	$db->query("UPDATE card_info SET is_used=unix_timestamp(NOW()) where id=".$r['id']);
	$db->query("UPDATE card_pool_info SET cards_used=cards_used-1 where pool_id='$poll_id'", $db);

	$cmd = UTM_CMD."-a add_payment -account_id ".$account_id." -payment ".$r['balance']." -payment_method 3 2>&1";
	my_exec($cmd);
	
	return true;
}


function send_pass_message()
{
    global $db,$vars;
    
    $r = $db->fetch_row_array("
    SELECT u.password as p FROM users u, accounts a where (u.login='".$vars['login']['val']."' or  a.external_id='".$vars['login']['val']."') and a.is_deleted=0 
    and u.is_deleted=0 and a.id=u.basic_account 
    #and a.external_id like '21%' 
    ");
    if(!is_array($r)) return "Не найден логин ".$vars['login']['val']." ".$r['p'];
    $password = $r['p'];
    
    if ($vars['m_type']['val'] == 1)
    {
	$r = $db->fetch_row_array("SELECT email FROM users u, accounts a  where u.basic_account=a.id and (u.login='".$vars['login']['val']."' or  a.external_id='".$vars['login']['val']."') AND a.is_deleted=0  and email!=''");
        if(!is_array($r)) return "Для логина ".$vars['login']['val']." не указан почтовый ящик";
	
	$email = $r['email'];
	$message_subj='Напоминание пароля';
	$fromname="техподдержка xxxxx";
	$message_body = "Ваш пароль для доступа в личный кабинет $password";
	
	$to  = "$email" ;
	$subject = "xxxxx пароль";
	$subject = '=?koi8-r?B?'.base64_encode(iconv ("utf-8","koi8-r",$subject)).'?=';
	$message = "$message_body";
	
	$headers  = "Content-type: text/plain; charset=utf-8 \r\n";
	$headers .= "From: =?koi8-r?B?" . base64_encode( iconv( "utf-8", "koi8-r", 'xxxxx' ) ) . "?= <noreply@xxxx.xx>\r\n";
	#$headers .= "Bcc: birthday-archive@example.com\r\n";
	
	mail($to, $subject, $message, $headers);
	
	
	/*$mailer = new CMIMEMail();
	if ($IsHtml) $mailer->mailbody(strip_tags($message_body), $message_body);
	else $mailer->mailbody($message_body);
	$mailer->send($UserEmail, $fromemail, $fromemail, $message_subj, $fromname);
	*/
	//$cmd ="date  > /dev/null";
	//return "Для логина ".$vars['login']['val']." пароль выслан на Е-mail";
	//$sms_msg= "Для логина ".$vars['login']['val']." пароль выслан на Е-mail";
	
    }
    if ($vars['m_type']['val'] == 0)
    {
	$r = $db->fetch_row_array("SELECT mobile_telephone FROM users u, accounts a  where u.basic_account=a.id and (u.login='".$vars['login']['val']."' or  a.external_id='".$vars['login']['val']."') 
	 AND a.is_deleted=0 and mobile_telephone!='' ");
	if(!is_array($r)) return "Для логина ".$vars['login']['val']." не указан номер мобильного телефона";
	$mob = $r['mobile_telephone'];
	$mob=$mob-10000000000;
	$message = "Ваш пароль для доступа в личный кабинет $password";
    	$url= "http://ffffffffffff.ru/_getsmsd.php?user=xxxxxxx&password=xxxxxxx&sender=xxxx&SMSText=$message&GSM=$mob&messageId=001";
    	
    	$cmd ="GET '".$url."'  > /dev/null";
    	my_exec( $cmd );
    	$status='ok';
    	//return "Для логина ".$vars['login']['val']." пароль выслан на номер мобильного телефона";
    	
    } 
    //return my_exec($cmd);           
    return true;
    //return $url;
}


function send_pass_message_xxxxxxx()
{
global $db,$vars;

$r = $db->fetch_row_array("
SELECT u.password as p, u.id as uid, u.login FROM users u, accounts a where (u.login='".$vars['login']['val']."' or  a.external_id='".$vars['login']['val']."')  and a.is_deleted=0
and u.is_deleted=0 and a.id=u.basic_account
#and a.external_id like '10%'
and a.external_id !=10116556
");
if(!is_array($r)) return "Не найден лицевой счет ".$vars['login']['val']." ".$r['p'];

$password=md5(uniqid(rand(),true));
$password=substr($password, -6);

$user_id = $r['uid'];
$login = $r['login'];
        $cmd = UTM_CMD."-a edit_user -user_id ".$user_id." -password ".$password." 2>&1";
	my_exec($cmd);
#$password = $r['p'];

    if ($vars['m_type']['val'] == 1)
    {
    $r = $db->fetch_row_array("SELECT email FROM users u, accounts a  where u.basic_account=a.id and (u.login='".$vars['login']['val']."' or  a.external_id='".$vars['login']['val']."')
     AND a.is_deleted=0  and email!=''");
    if(!is_array($r)) return "Для лицевого счета ".$vars['login']['val']." не указан адрес электронной почты";

    $email = $r['email'];
    $message_subj='Напоминание пароля';
    $fromname="техподдержка zzzzzzz";
    $message_body = "Ваш пароль для доступа в личный кабинет $password";

    $to  = "$email" ;
    $subject = "zzzzzzz пароль";
    $subject = '=?koi8-r?B?'.base64_encode(iconv ("utf-8","koi8-r",$subject)).'?=';
    $message = "$message_body";

    $headers  = "Content-type: text/plain; charset=utf-8 \r\n";
    $headers .= "From: =?koi8-r?B?" . base64_encode( iconv( "utf-8", "koi8-r", 'zzzzzzz' ) ) . "?= <noreply@xxxxxxx.ru>\r\n";
#$headers .= "Bcc: birthday-archive@example.com\r\n";

    mail($to, $subject, $message, $headers);


/*$mailer = new CMIMEMail();
if ($IsHtml) $mailer->mailbody(strip_tags($message_body), $message_body);
else $mailer->mailbody($message_body);
$mailer->send($UserEmail, $fromemail, $fromemail, $message_subj, $fromname);
*/
//$cmd ="date  > /dev/null";
//return "Для логина ".$vars['login']['val']." пароль выслан на Е-mail";
//$sms_msg= "Для логина ".$vars['login']['val']." пароль выслан на Е-mail";
   }
   if ($vars['m_type']['val'] == 0)
   {
   $r = $db->fetch_row_array("SELECT mobile_telephone  FROM users u, accounts a  where u.basic_account=a.id and (u.login='".$vars['login']['val']."' or  a.external_id='".$vars['login']['val']."')
    AND a.is_deleted=0 and mobile_telephone!='' ");
   if(!is_array($r)) return "Для лицевого счета ".$vars['login']['val']." не указан номер мобильного телефона";
   $mob = $r['mobile_telephone'];
   $mob=$mob-10000000000;

   $message = "Ваш пароль для доступа в личный кабинет $password";
   $url= "http://ffffffffffffru/_getsmsd.php?user=xxxxxxx&password=xxxxxx&sender=xxxxxxx&SMSText=$message&GSM=$mob&messageId=001";
   $cmd ="GET '".$url."'  > /dev/null";
   my_exec( $cmd );
   $status='ok';
   //return "Для логина ".$vars['login']['val']." пароль выслан на номер мобильного телефона";
   
   }
   //return my_exec($cmd);
   return true;
  //return $url;
}
   



function send_sms_balance()
{
    global $db,$vars;
    return false;

$sql = "select a.id, a.balance, a.credit, u.mobile_telephone, uap.value firm from accounts a 
join users u on u.basic_account = a.id 
join user_additional_params uap on ( uap.userid = u.id and uap.paramid = 2 ) 
join blocks_info bi on bi.account_id = a.id and bi.start_date >= unix_timestamp( '2014-01-09 09:00:00' ) 
and bi.start_date <= unix_timestamp( '2014-01-09 11:00:00' ) and bi.expire_date = 2000000000 
where u.is_juridical=0 and a.is_blocked<>0 and a.is_deleted=0 and u.is_deleted = 0;";

$q = $db->query($sql);

$i = 0;
while($a = $db->fetch_array($q))
{
    $firm="";
    $from = "";
    $end="";
    $firm=$a['firm'];
    if( $firm == 'zzzzzzz' ) 
    {
        $from = "xxxxxxx";
        $end = "Ваш zzzzzzz.";
    }
    elseif( $firm == 'xxxxx' )
    {
        $from = "xxxx";
        $end = "Интернет провайдер xxxxx.";
    }
    else
        continue;
#$r = $db->fetch_row_array("SELECT a.balance,a.credit,u.mobile_telephone from accounts a, users u where a.id=u.basic_account and a.id= ".$a['id']." "); // and  credit=0 ");
    $balance = $a['balance'];
#$credit = $a['credit'];
    $credit = 0;
    $mphone = $a['mobile_telephone'];
    if( $mphone+0 < 1 )
        continue;

#   $r1 = $db->fetch_row_array("select ifnull(sum(psd.cost),0) as s from service_links sl, services_data sd, periodic_services_data psd 
#where sl.service_id=sd.id and sd.id=psd.id and sd.is_deleted=0 and sl.is_deleted=0 and psd.is_deleted=0 and sl.account_id=".$a['id']." ");
#   $sum1= $r1['s'];

#   $sum= $r1['s'];
#   if ($sum >0)

#       {

#   $sum=$sum-($balance+$credit);
    $sum = -$balance;

    if ($sum >0)
            {
    $mob=$mphone-10000000000;
    #$message = sprintf( "Не забудьте внести платеж за январь. Сумма к оплате %d руб. %s", $sum+1, $end );
    $message = sprintf( "Уважаемый абонент! Для возобновления услуг связи просим Вас внести очередной платеж. Сумма к оплате составляет %d руб. %s", $sum+1, $end );
    
    $url= "http://fffffffff.ru/_getsmsd.php?user=xxxxxxx&password=xxxxxx&sender=$from&SMSText=$message&GSM=$mob&messageId=001";
    echo ++$i . " " . $a['id'] . " url $url<br>";
    $cmd ="GET '".$url."'  > /dev/null";
    my_exec( $cmd );
            }
#	}
}
return true;

}

function send_mass_message()
{
    global $db,$vars;
    
$sql = "
select u.email as email from users u,accounts a 
where a.id=u.basic_account and a.is_deleted=0 and u.is_deleted=0 and u.email!='' 
and is_juridical=0 and u.email!='' and u.email like '%@%' and a.external_id<21200000
#and a.id=17790 limit 1
";


$sql1="select trim(mobile_telephone) as mob from  users u, accounts a, service_links sl
where a.id=u.basic_account and a.is_deleted=0 and u.is_deleted=0 and a.id=sl.account_id and sl.is_deleted=0
and a.external_id<21200000 and trim(mobile_telephone)!=''
and sl.service_id=1084
#and a.id=17790 limit 1
";

$q = $db->query($sql1);
while($a = $db->fetch_array($q))
    {
    $email=$a['email'];
    $from='xxxxxxx';
    // xxxxxxx
    $mob=$a['mob'];
    $mob= ( "7" . substr( $mob + 0, -10 ) ) + 0;
    $message ="
Уважаемые абоненты!

Информируем Вас об изменении цен на услугу предоставления и поддержание (использование) внешнего статического IP-адреса.
    ";
    
    $message1="Уважаемый абонент! С 01.03.15 аб.плата за внешний IP-адрес 100 р./мес.";
    echo $email;
    //echo $firm;
    echo "$mob </br>";
    echo "$message1</br>";
////// if SMS
$url= "http://fffffffffff.ru/_getsmsd.php?user=xxxxxxx&password=xxxxxx&sender=$from&SMSText=" . urlencode( $message1 ) . "&GSM=$mob&messageId=001";
echo ++$i . " , " . $a['id'] . "$message1, url $url<br>";
$cmd ="GET '".$url."'  > /dev/null";
//my_exec( $cmd );


//if email
$subject = "Изменение цен на услугу предоставления и поддержание (использование) внешнего статического IP-адреса.";
$subject = '=?koi8-r?B?'.base64_encode(iconv ("utf-8","koi8-r",$subject)).'?=';
$message = "$message";
$headers  = "Content-type: text/plain; charset=utf-8 \r\n";
$headers .= "From: =?koi8-r?B?" . base64_encode( iconv( "utf-8", "koi8-r", 'zzzzzzz' ) ) . "?= <noreply@xxxxxxx.ru>\r\n";
//$headers .= 'Cc: m@xxxxxxx.ru' . "\r\n";
//            mail($email, $subject, $message, $headers);                                                

    
    }
return true;
}




function send_sms_payment()
{
    global $db,$vars;
//return false; // чтоб не запускался скрипт 




//    #round(a.balance,0)-1 это для игнорирования копеек и увеличения и округления сумы оплаты.
//   # floor(a.balance) округляет до меньшего целого
$sql = "select a.id, a.external_id, u.full_name, u.actual_address, u.email,
floor(a.balance) as balance,
round(a.credit,2) as credit, u.mobile_telephone, uap.value firm from accounts a
join users u on u.basic_account = a.id
join user_additional_params uap on ( uap.userid = u.id and uap.paramid = 2 )
where u.is_juridical=0  and a.is_blocked =0 and a.is_deleted=0 and u.is_deleted = 0 
and a.external_id not like '213%'
and a.id not in (select account_id from account_tariff_link where next_tariff_id in (271,408,409,508,375,377,376,499) and is_deleted=0)";

$sql_jur = "select a.id, a.external_id, u.full_name, u.actual_address, u.email,
floor(a.balance) as balance,
round(a.credit,2) as credit, u.mobile_telephone, 'zzzzzzz' firm from accounts a
join users u on u.basic_account = a.id
where  a.is_deleted=0 and u.is_deleted = 0
and a.is_blocked=0
and u.is_juridical=1

and a.id not in (select account_id from account_tariff_link where next_tariff_id in (271,408,409,508,375,377,376,499) and is_deleted=0)";
 
//$q = $db->query($sql_jur);
$q = $db->query($sql);


$i = 0;

while($a = $db->fetch_array($q))
    {

$firm="";
$from = "";
$mess1 = "";
$mess2 = "";
$mess3 = "";
$external_id=$a['external_id'];
$full_name=$a['full_name'];
$actual_address=$a['actual_address'];
$email=$a['email'];
$firm=$a['firm'];
if( $firm == 'zzzzzzz' )
	{
$from = "xxxxxxx";
//$mess1 = "Уважаемый Абонент! Ваш баланс: ";
//$mess2 = " руб. Не забудьте внести платеж за следующий месяц. Сумма к оплате ";
//$mess3 = " руб. Ваш zzzzzzz.";
$mess1 = "";
$mess2 = "Уважаемый абонент, сумма к оплате за Сентябрь ";
$mess3 = " руб.";
$mess4 = " Л/c $external_id.";



	}
elseif( $firm == 'xxxxx' )
	{
$from = "xxxxxxx";
$mess1 = "";
$mess2 = "Наш xxxxxмый абонент! Сумма к оплате за Сентябрь ";
$mess3 = " руб.";
$mess4 = " Л/c $external_id.";
	}
else
continue;
#$r = $db->fetch_row_array("SELECT a.balance,a.credit,u.mobile_telephone from accounts a, users u where a.id=u.basic_account and a.id= ".$a['id']." "); // an
$balance = $a['balance'];
$account_id=$a['id'];

#echo $balance; 

#$credit = $a['credit'];
$credit = 0;
$mphone = $a['mobile_telephone'];
    //if( strlen( $mphone+0 ) < 10 )
    //continue;


$r1 = $db->fetch_row_array("select ifnull(sum(psd.cost),0) as s from service_links sl, 
services_data sd, periodic_services_data psd where sl.service_id=sd.id 
and sd.id=psd.id and sd.is_deleted=0 and sl.is_deleted=0 and psd.is_deleted=0
and (sd.tariff_id=0 or sd.link_by_default=0) and sl.account_id=".$a['id']." ");

$sum1= $r1['s'];
#echo "сумма1 $sum1 ,";
$r2 = $db->fetch_row_array("select ifnull(sum(psd.cost),0) as s from services_data sd, periodic_services_data psd, account_tariff_link atl
where sd.id=psd.id and sd.is_deleted=0 and psd.is_deleted=0 and atl.is_deleted=0
and sd.link_by_default=1 and sd.service_type!=1 and sd.tariff_id=atl.next_tariff_id and atl.account_id=".$a['id']." ");

$sum2= $r2['s'];
    
    $sum=$sum1+$sum2;
    if ($sum >0)
    	{
    	
	$sum=$sum - $balance;//закоментировал для статистики
	if ($sum >0)
	    {
$mob= ( "7" . substr( $mphone + 0, -10 ) ) + 0;
#$mob=$mphone-10000000000;

    if( $firm == 'zzzzzzz' )
		{
	$message = $mess2.$sum.$mess3.$mess4;
	//( rand(0,1) ? $mess4: "" );
		}
    elseif( $firm == 'xxxxx' )
		{
	$message = $mess2.$sum.$mess3.$mess4;    
		}
    else continue;
    
// SMS  для физ лиц                   
//echo ++$i . " , " . $a['id'] . "$message, url $url<br>";
//$cmd ="GET '".$url."'  > /dev/null";
//my_exec( $cmd );


//для статистики
//echo "$external_id*,$full_name*,$actual_address*,$mphone*, $sum <br>" ;
//echo "$message</br>";
//for credit 

//кредиты для физ лиц
//#select unix_timestamp('2015-08-01 10:00:00');
//#select unix_timestamp('2015-08-01 18:00:00');
//$burn_date=rand (1438412400,1438441200);
#echo date('r',$burn_date);
//strtotime($burn_date);
//$cmd= UTM_CMD." -a add_credit_payment -account_id $account_id -payment $sum -currency_id 810 -burn_date $burn_date -payment_method 7 -turn_on_inet 1";
//echo ++$i. "$cmd </br>";
//my_exec( $cmd );




// кредиты для юр лиц
//select unix_timestamp('2015-09-04 10:30:00');
//select unix_timestamp('2015-09-04 12:30:00');
#//$burn_date='1438675200';
//$burn_date=rand (1441351800,1441359000);
//$sum=round($sum*1.2,0);
//echo ++$i." ".$external_id." ".$balance." ".$credit." ".$sum." ".$full_name." ".$mphone." ".$email."</br>";
//$cmd= UTM_CMD." -a add_credit_payment -account_id $account_id -payment $sum -currency_id 810 -burn_date $burn_date -payment_method 7 -turn_on_inet 1";
//echo ++$i. "$cmd </br>";
//my_exec( $cmd );

	    }
	}
    }
    return true;
}





function send_jur_payment_message()
{
    global $db,$vars;

    if(!$vars['m_type']['val']) return "m_type number value is bad.";
            
$m_type=$vars['m_type']['val'];

#return false; // чтоб не запускался скрипт
#return "comment me please, coment me polnost'yu, line 980 in /www/vhost/dataserver/inc/functions_set.php";

//#round(a.balance,0)-1 это для игнорирования копеек и увеличения и округления сумы оплаты.
# floor(a.balance) округляет до меньшего целого

$sql_jur = "select a.id, a.external_id, u.full_name, u.actual_address, u.email,
floor(a.balance) as balance,
round(a.credit,2) as credit, u.mobile_telephone, 'zzzzzzz' firm from accounts a
join users u on u.basic_account = a.id
where  a.is_deleted=0 and u.is_deleted = 0
and a.is_blocked=0
and a.id in
(
457,8698,21808,571,975,1166,1328,1352,1602,10075,1641,1857,2033,2109,2760,2972,9551,3058,3210,3237,3278,3427,21509,3688,
4423,4894,5051,5122,5641,5720,5749,5758,6252,17759,6376,7368,7683,7813,7985,8536,8554,8677,12574,13587,13953,14092,14254,
14330,22362,14512,14563,14717,15057,15370,15647,15665,15919,16075,16606,16946,17236,17316,6042,17816,17488,17763,16097,18081,
18081,18371,18459,18492,15202,18753,18937,19313,19775,19703,19641,19760,20202,20243,20409,20585,20280,20814,21066,21121,20942,
22084,21274,1070,21147,21832,18081,21592,21594,21549,21508,21609,21615,21623,21828,21722,21829,21845,21846,21610,21818,21831,
21847,21688,21924,17491,21991,21957,21993,22016,22017,22062,22083,1165,22035,22064,22288,22289,960,22361,22437,22408,15781,
22478,14532,22667,22708,22708
)
#and a.id=18389

and a.id not in (select account_id from account_tariff_link where next_tariff_id in (271,408,409,508,375,377,376,499) and is_deleted=0)";

$q = $db->query($sql_jur);
//$q = $db->query($sql);

$i = 0;

while($a = $db->fetch_array($q))

    {
    
    $external_id=$a['external_id'];
    $full_name=$a['full_name'];
    $actual_address=$a['actual_address'];
    $email=$a['email'];
    $firm=$a['firm'];
    
    $balance = $a['balance'];
    #$credit = 0;
    $mphone = $a['mobile_telephone'];
    //if( strlen( $mphone+0 ) < 10 )
    //continue;
    
    //echo $email;
    
    $r1 = $db->fetch_row_array("select ifnull(sum(psd.cost),0) as s from service_links sl,
    services_data sd, periodic_services_data psd where sl.service_id=sd.id
    and sd.id=psd.id and sd.is_deleted=0 and sl.is_deleted=0 and psd.is_deleted=0
    and (sd.tariff_id=0 or sd.link_by_default=0) and sl.account_id=".$a['id']." ");
    
    $sum1= $r1['s'];
    
    #echo "сумма1 $sum1 ,";
    $r2 = $db->fetch_row_array("select ifnull(sum(psd.cost),0) as s from services_data sd, periodic_services_data psd, account_tariff_link atl
    where sd.id=psd.id and sd.is_deleted=0 and psd.is_deleted=0 and atl.is_deleted=0
    and sd.link_by_default=1 and sd.service_type!=1 and sd.tariff_id=atl.next_tariff_id and atl.account_id=".$a['id']." ");
    
    $sum2= $r2['s'];
    
    $sum=$sum1+$sum2;
// email $email
// sum $sum
// balance $balance
// dif $dif
    
//   echo "name $full_name </br>";
//  echo "email $email </br>";                                    
//    echo "sum $sum </br>";
//    echo "balance $balance </br>";
    
	//if ($sum >$balance)
	//{

	$dif=$balance - $sum;
//	echo "dif $dif</br>";
	$mons = array(1 => "Январь", 2 => "Февраль", 3 => "Март", 4 => "Апрель", 5 => "Май", 6 => "Июнь", 7 => "Июль", 8 => "Август", 9 => "Сентябрь", 10 => "Октябрь", 11 => "Ноябрь", 12 => "Декабрь");

	$date = getdate();

	if ($m_type==2 && $sum >$balance)
	    {    


#    echo "name $full_name </br>";
#    echo "email $email </br>";
#    echo "sum $sum </br>";
#    echo "balance $balance </br>";
#    echo "dif $dif</br>";                


    	    $year = date("Y", strtotime(" +1 months"));
	    $month = date("n", strtotime(" +1 months"));
	    $month_name = $mons[$month];
		
	    $mes="
	    Уважаемый абонент, $full_name.
	    
	 В свою очередь уведомляем Вас о том, что у Вас не оплачен счет за услуги связи на следующий месяц "; 
	    $mes.="$month_name $year г., ";
	    $mes.=" просим Вас оплатить счета во избежании автоматической блокировки услуг связи, в первых числах нового месяца.

	
	    С Уважением
	    Ваш оператор связи.
	    Для улучшения качества услуг просим Вас оставлять заявки по e-mail: info@xxxxxxx.ru";    

	    $to  = "$email" ;
	    //$to  = "m@xxxxxxx.ru" ;
	    
	    $subject = "Напоминание об оплате услуг связи";
	    $subject = '=?koi8-r?B?'.base64_encode(iconv ("utf-8","koi8-r",$subject)).'?=';
	    $message = "$mes";
	    $headers  = "Content-type: text/plain; charset=utf-8 \r\n";
	    $headers .= "From: =?koi8-r?B?" . base64_encode( iconv( "utf-8", "koi8-r", 'zzzzzzz' ) ) . "?= <noreply@xxxxxxx.ru>\r\n";
	    $headers .= 'Cc: u@xxxxxxx.ru' . "\r\n";
	    //mail($to, $subject, $message, $headers);

	    }

	    if ($m_type==1 && $balance < 0)
            {
#    echo "name $full_name </br>";
#    echo "email $email </br>";
#    echo "sum $sum </br>";
#    echo "balance $balance </br>";
#    echo "dif $dif</br>";
                    
	    $year = date("Y", strtotime(" +0 months"));
	    $month = date("n", strtotime(" +0 months"));
	    $month_name = $mons[$month];

	    $mes="
	    Уважаемый абонент, $full_name.
	    
у Вас не оплачен счет за услуги связи текущего месяца ";
	    $mes.="$month_name $year г.,";
	    $mes.=" просим Вас оплатить счета во избежании автоматической блокировки услуг связи 15 числа текущего месяца.


	    С Уважением
	    Ваш оператор связи.
	    Для улучшения качества услуг просим Вас оставлять заявки по e-mail: info@xxxxxxx.ru";

	    $to  = "$email";
	    //$to  = "m@xxxxxxx.ru" ;
	    $subject = "Напоминание об оплате услуг связи";
	    $subject = '=?koi8-r?B?'.base64_encode(iconv ("utf-8","koi8-r",$subject)).'?=';
	    $message = "$mes";
	    $headers  = "Content-type: text/plain; charset=utf-8 \r\n";
	    $headers .= "From: =?koi8-r?B?" . base64_encode( iconv( "utf-8", "koi8-r", 'zzzzzzz' ) ) . "?= <noreply@xxxxxxx.ru>\r\n";
	    $headers .= 'Cc: u@xxxxxxx.ru' . "\r\n";
	    //mail($to, $subject, $message, $headers);

	    }
	
    }
    return true;
}



function email_notification()
//xxxxxxx АБОНЕНТЫ ТЕЛЕФОНИИ 
{
global $db,$vars;


$sql = "

select a.external_id, a.is_blocked, u.email, concat(case
when ltg.name = 'Основная' then 'Начисления по тарифам Интернет'
when ltg.name = 'Общежитие' then 'Начисления по тарифам Интернет'
when ltg.name = 'DUMMY' then 'Начисления по тарифам Интернет'
when ltg.name = 'Автоматические' then 'Начисления по тарифам Интернет'
when ltg.name = 'Новогорск' then 'Начисления по тарифам Интернет'
when ltg.name = 'Школа' then 'Начисления по тарифам Интернет'
when ltg.name = 'ТВ' then 'Начисления по тарифам Телевидение'
when ltg.name = 'Телефония' then 'Телефония'
end
, case when u.is_juridical = '0' then ' ФЛ' when u.is_juridical = '1' then ' ЮЛ' end ) as sservice_type
from users u
join accounts a on a.id=u.basic_account
join account_tariff_link atl on a.id=atl.account_id
join tariffs t on atl.tariff_id=t.id
join lru_tarif lt on lt.tarif_id=t.id
join lru_tarif_group ltg on lt.tarif_group_id=ltg.id and ltg.name = 'Телефония'
where
a.is_deleted=0 and u.is_deleted=0
and atl.is_deleted=0
and u.email!=''
and a.external_id<21200000
and a.is_blocked!=1792
group by a.external_id
";

$q = $db->query($sql);

$i = 1;

    while($a = $db->fetch_array($q))

	{
	$external_id=$a['external_id'];
	$email=$a['email'];


	$mes="
Уважаемый абонент!

	";

//$to  = "$email" ;
$to  = "m@xxxxxxx.ru" ;

$subject = "Напоминание об оплате услуг связи";
$subject = '=?koi8-r?B?'.base64_encode(iconv ("utf-8","koi8-r",$subject)).'?=';
$message = "$mes";
$headers  = "Content-type: text/plain; charset=utf-8 \r\n";
$headers .= "From: =?koi8-r?B?" . base64_encode( iconv( "utf-8", "koi8-r", 'zzzzzzz' ) ) . "?= <noreply@xxxxxxx.ru>\r\n";
//$headers .= 'Cc: u@xxxxxxx.ru' . "\r\n";
//mail($to, $subject, $message, $headers);
echo "$i</br>";
$i++;

	}
    return true;
}



function change_speed_tariff()
{

//return false;

global $db,$vars;

$sql = "
select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name, 
(select  ifnull(min(t1.id),t.id) from tariffs t1 where t1.id>t.id and t1.name like '%xxxxx Ускорение%' and is_deleted=0) as next_t_id 

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id
where t.name like '%xxxxx Ускорение%' and atl.is_deleted=0 and a.is_blocked=0 and atl.tariff_id=atl.next_tariff_id 
and atl.account_id not in 
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type =1
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' )))


Union all


select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name, 
(select min(t1.id) from tariffs t1 where t1.name like '%xxxxx Ускорение%') as next_t_id 

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id

where t.name like '%xxxxx Ускорение%' and atl.is_deleted=0 and atl.tariff_id=atl.next_tariff_id 

and (a.is_blocked!=0 or atl.account_id in 
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type !=2
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' ))))


Union all


select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name, 
(select ifnull(min(t1.id),t.id) from tariffs t1 where t1.id>t.id and t1.name like '%Выбери меня%'and is_deleted=0) as next_t_id

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id
where t.name like '%Выбери меня%' and atl.is_deleted=0 and a.is_blocked=0 and atl.tariff_id=atl.next_tariff_id
and atl.account_id not in 
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type =1
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' )))


Union all


select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name, 
(select min(t1.id) from tariffs t1 where t1.name like '%Выбери меня%') as next_t_id

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id

where t.name like '%Выбери меня%' and atl.is_deleted=0 and atl.tariff_id=atl.next_tariff_id

and (a.is_blocked!=0 or atl.account_id in 
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type !=2
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' ))))


Union all

select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name, 
(select ifnull(min(t1.id),t.id) from tariffs t1 where t1.id>t.id and t1.name like '%Влюбленность%' and is_deleted=0 ) as next_t_id

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id
where t.name like '%Влюбленность%' and atl.is_deleted=0 and a.is_blocked=0 and atl.tariff_id=atl.next_tariff_id
and atl.account_id not in 
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type =1
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' )))


Union all


select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name, 
(select min(t1.id) from tariffs t1 where t1.name like '%Влюбленность%') as next_t_id

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id

where t.name like '%Влюбленность%' and atl.is_deleted=0 and atl.tariff_id=atl.next_tariff_id

and (a.is_blocked!=0 or atl.account_id in 
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type !=2
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' ))))


Union all

select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name, 
(select ifnull(min(t1.id),t.id) from tariffs t1 where t1.id>t.id and t1.name like '%xxxxx Расти%' and t1.name not like '%xxxxx Расти 2015%' and is_deleted=0) as next_t_id

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id
where t.name like '%xxxxx Расти%' and t.name not like '%xxxxx Расти 2015%' and atl.is_deleted=0 and a.is_blocked=0 and atl.tariff_id=atl.next_tariff_id
and atl.account_id not in 
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type =1
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' )))


Union all


select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name, 
(select min(t1.id) from tariffs t1 where t1.name like '%xxxxx Расти%' and t1.name not like '%xxxxx Расти 2015%') as next_t_id

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id

where t.name like '%xxxxx Расти%' and t.name not like '%xxxxx Расти 2015%' and atl.is_deleted=0 and atl.tariff_id=atl.next_tariff_id

and (a.is_blocked!=0 or atl.account_id in 
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type !=2
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' ))))

Union all

select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name,
(select ifnull(min(t1.id),t.id) from tariffs t1 where t1.id>t.id and t1.name like '%xxxxx Расти 2015%' and is_deleted=0) as next_t_id

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id
where t.name like '%xxxxx Расти 2015%' and atl.is_deleted=0 and a.is_blocked=0 and atl.tariff_id=atl.next_tariff_id
and atl.account_id not in
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type =1
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' )))


Union all


select u.id, a.is_blocked, atl.account_id, a.external_id, atl.tariff_id, atl.id as tlink_id, t.name,
(select min(t1.id) from tariffs t1 where t1.name like '%xxxxx Расти 2015%') as next_t_id

from account_tariff_link atl

right join accounts a on atl.account_id=a.id
right join users u on atl.account_id=u.basic_account

left join tariffs t on atl.tariff_id=t.id

where t.name like '%xxxxx Расти 2015%' and atl.is_deleted=0 and atl.tariff_id=atl.next_tariff_id

and (a.is_blocked!=0 or atl.account_id in
(select bi.account_id from blocks_info bi where bi.expire_date-bi.start_date>86400 and bi.block_type !=2
and bi.start_date > UNIX_TIMESTAMP(FROM_UNIXTIME(UNIX_TIMESTAMP(now()),'%Y-%m-01 00:00:01' ))))


";
$q = $db->query($sql);

while($t = $db->fetch_array($q))
{

$external_id=$t['external_id'];
$tariff_id=$t['tariff_id'];
$next_t_id=$t['next_t_id'];
$tlink_id=$t['tlink_id'];

#в текущем месяце - сменить сейчас,(если проебали в конце предыдущего месяца запустить скрипт)
#в следующем месяце - запланировать смену
$url= "https://xxxxxxx.ru/dataserver/query.php?cmd=change_tariff&external_id=$external_id&old_tariff_id=$tariff_id&new_tariff_id=$next_t_id&tlink_id=$tlink_id";
$cmd ="GET '".$url."' > /dev/null";
echo "$cmd </br>";
my_exec( $cmd );

//return $cmd;
    }
return true;

}





function call_request()
    {
    global $db,$vars;

    if(!$vars['telephone']['val']) return "phone value is bad.";
    if(!$vars['firm']['val']) return "firm value is bad.";
    if(!$vars['full_name']['val']) return "full_name value is bad.";

//$phone="$vars['telephone']['val']"
	if ($vars['firm']['val']=='zzzzzzz')
	{
	/* отправка почты на zzzzzzz
	    $email = 'info@xxxxxxx.ru';
	    $message_subj='Встречный звонок zzzzzzz';
	    $fromname="zzzzzzz";
	    $message_body1 = $vars['telephone']['val'] ;
	    //$message_body =.,  ;
	    $message_body2 =$vars['full_name']['val'] ;
	    
	    $to  = "$email" ;
	    $subject = "$message_subj";
	    $subject = '=?koi8-r?B?'.base64_encode(iconv ("utf-8","koi8-r",$subject)).'?=';
	    $message = "$message_body1 , $message_body2";
	    $headers  = "Content-type: text/plain; charset=utf-8 \r\n";
	    
	    $headers .= "From: =?koi8-r?B?" . base64_encode( iconv( "utf-8", "koi8-r", 'zzzzzzz' ) ) . "?= <pbx@xxxxxxx.ru>\r\n";
	    //mail($to, $subject, $message, $headers);
	 /отправка почты на zzzzzzz */

//запуск обратного звонка в октеле

$link = mssql_connect('xxx.xxx:4858\OKTELL', 'ffffffffffff', 'eeeee');

if (!$link || !mssql_select_db('oktell', $link)) {
die('Unable to connect or select database!!!');
}

$telephone = $vars['telephone']['val'] ;
$name =$vars['full_name']['val'] ;

$telephone=iconv("UTF-8", "WINDOWS-1251", $telephone);                        
$telephone=  substr( $telephone + 0, -10 ) ;

$name=iconv("UTF-8", "WINDOWS-1251", $name);
$date = date("Y-m-d H:i:s");


$sql = mssql_query("insert into dbo.callback_xxxxxxx (number,name,date) values 
('".$telephone."','".$name."','".$date."') ");

mssql_close();

	}
	if ($vars['firm']['val']=='xxxxx')
	{
	    $email = 'm@xxxxxxx.ru';
	    $message_subj='Встречный звонок xxxxx';
	    $fromname="xxxxx";
	    $message_body = $vars['telephone']['val'];
	    
	    $to  = "$email" ;
	    $subject = "$message_subj";
	    $subject = '=?koi8-r?B?'.base64_encode(iconv ("utf-8","koi8-r",$subject)).'?=';
	    $message = "$message_body1 , $message_body2";

	    $headers  = "Content-type: text/plain; charset=utf-8 \r\n";
	    $headers .= "From: =?koi8-r?B?" . base64_encode( iconv( "utf-8", "koi8-r", 'xxxxx' ) ) . "?= <pbx@xxxxxxx.ru>\r\n";
	    
	    //отправка писем xxxxx перенесена в xxxxx потому что zzzzzzzу сделали отзвон
	    mail($to, $subject, $message, $headers);
	}
    //общую отправку почты отменили  и xxxxx отправляется отдельно а zzzzzzz идет в отзвон
    //mail($to, $subject, $message, $headers);
    return true;
    }




function comment_xxxxxxx()
    {
    global $db,$vars;
    $call_center = 9;
    $abon_otdel =13;

    if(!$vars['full_name']['val']) return "Full_name value is bad.";
    if(!$vars['telephone']['val']) return "phone value is bad.";
    if(!$vars['email']['val']) return "email value is bad.";
    if(!$vars['comment']['val']) return "comment value is bad.";

    $r1 = $db->query("set character set utf8");

    $h_id=0;
    $flat_number=0;
//создать заявку на подключение в юзерсайд для zzzzzzzа
    $sql = "insert into userside.tbl_journal (
    STATUS, HOUSECODE, TYPER, USERCODE, DATEADD, DATEDO, OPIS, NEWKLIENT, FROMAPROVE, UZELCODE, PRIORITY, APART )
    values (
    1, $h_id, 37, 0, NOW(), NOW(), 'Телефон: " . $vars['telephone']['val']
    . "\n E-mail: " . $vars['email']['val']
    . "\nКоментарий:\n". $vars['comment']['val'] . "', '" . $vars['full_name']['val'] . "', '', 0, 0, '"
    . $flat_number. "' )";

$db->query( $sql );

// Назначение заявки сотрудникам
    $r = $db->fetch_row_array( "select max(CODE) c from userside.tbl_journal" );
    $new_id = $r['c'];
    $sql = "insert into userside.tbl_journal_staff (JOURNALCODE, PERSCODE, ISPODRAZD ) values
    ( $new_id, $abon_otdel, 1 )
, ( $new_id, $call_center, 1)";
    $db->query( $sql );

    return true;
    }



function new_connection()
{
    global $db,$vars;
    $abon_otdel = 3;
    $call_center = 9;

    if(!$vars['full_name']['val']) return "Full_name value is bad.";
    if(!$vars['street']['val'])  return "Неверно Указан адрес.";
    if(!$vars['house']['val']) return "house value is bad.";
    if(!$vars['flat_number']['val']) return "flat_number value is bad.";
    if(!$vars['telephone']['val']) return "phone value is bad.";
    
    $r1 = $db->query("set character set utf8");
    $sql = "select 
    h.CODE as CODE
    , h.STREETCODE 
FROM 
    userside.tbl_house as h
    join userside.tbl_street as s on s.code = h.streetcode 
where
    s.STREET='".$vars['street']['val']."' 
    and concat( h.HOUSE, h.HOUSE_B ) = '".$vars['house']['val']."'
    and ( h.ISDEL=0 or h.ISDEL is null ) 
    and ( s.ISDEL=0 or s.ISDEL is null )";
    
    $r = $db->fetch_row_array( $sql );
            
    if(!is_array($r)) return "Не корректно указан адрес подключения, вернитесь к шагу 1 и проверьте все ли поля заполнены.";
    //if(!is_array($r)) return "Not found house_id for ".$vars['street']['val'].", ".$vars['house']['val'] . ", " . $vars['flat_number']['val'] . "<br>$sql";
    $h_id = $r['CODE'];
    $s_id = $r['STREETCODE'];
	
     $sql = "insert into userside.tbl_journal (
            STATUS, HOUSECODE, TYPER, USERCODE, DATEADD, DATEDO, OPIS, NEWKLIENT, FROMAPROVE, UZELCODE, PRIORITY, APART ) 
     values (
             1, $h_id, 1, 0, NOW(), NOW(), 'Телефон: " . $vars['telephone']['val']
              . "\nинтернет:\n " . $vars['t_name1']['val'] . "\n" . $vars['s_name1']['val']
              . "\nТелефония:\n" . $vars['t_name2']['val'] . "\n" . $vars['s_name2']['val']
              . "\nТВ:\n" . $vars['t_name3']['val'] . "\n" . $vars['s_name3']['val'] 
              . "\nдополнительно:\n" . $vars['s_name4']['val'] 
              . "\nКоментарий:\n" . $vars['comment']['val'] . "', '" . $vars['full_name']['val'] . "', '', 0, 0, '" 
              . $vars['flat_number']['val'] . "' )";
              
     $db->query( $sql );
 
// Назначение заявки сотрудникам
	$r = $db->fetch_row_array( "select max(CODE) c from userside.tbl_journal" );
	$new_id = $r['c'];
	$sql = "insert into userside.tbl_journal_staff (JOURNALCODE, PERSCODE, ISPODRAZD ) values 
		( $new_id, $abon_otdel, 1 )
		, ( $new_id, $call_center, 1)";
	$db->query( $sql );
//echo $db->query;

    return true;
    }    


function new_connection_xxxxxxx()
    {
    global $db,$vars;
    $abon_otdel = 3;
    $call_center = 9;

if(!$vars['full_name']['val']) return "Full_name value is bad.";
if(!$vars['telephone']['val']) return "phone value is bad.";

$r1 = $db->query("set character set utf8");

$h_id=0;
$flat_number=0;
//создать заявку на подключение в юзерсайд для zzzzzzzа	
$sql = "insert into userside.tbl_journal (
STATUS, HOUSECODE, TYPER, USERCODE, DATEADD, DATEDO, OPIS, NEWKLIENT, FROMAPROVE, UZELCODE, PRIORITY, APART )
values (
1, $h_id, 36, 0, NOW(), NOW(), 
'<a href=\"/oper/utm5_contract_create.php?firm=%D0%A2%D0%95%D0%9B%D0%98%D0%9D%D0%9A%D0%9E%D0%9C&secret_key=".$vars['friend_1']['val'] . "&FIO=" . $vars['full_name']['val'] . "&tel1=" . $vars['telephone']['val']. "\">Перейти к составлению договора</a>\n'"
. "'Телефон: " . $vars['telephone']['val']
. ", Коментарий:" . $vars['comment']['val'] . "  '

, '" . $vars['full_name']['val'] . "', '', 0, 0, '"
. $flat_number. "' )";

$db->query( $sql );

// Назначение заявки сотрудникам
$r = $db->fetch_row_array( "select max(CODE) c from userside.tbl_journal" );
$new_id = $r['c'];
$sql = "insert into userside.tbl_journal_staff (JOURNALCODE, PERSCODE, ISPODRAZD ) values
( $new_id, $abon_otdel, 1 )
, ( $new_id, $call_center, 1)";
$db->query( $sql );

    return true;
    }





function new_request()
    {
    global $db,$vars;
    
    if($vars['request_type']['val']==1) {$request_type=10;} //покупка роутера
    if($vars['request_type']['val']==2) {$request_type=9;}  //подключение тв
    #if($vars['request_type']['val']==3) {$request_type=3;}  //подключение  тел
    #if($vars['request_type']['val']==4) {$request_type=13;} //подключение новвое инет
    
    $r1 = $db->query("set character set utf8");
    $r = $db->fetch_row_array("select tb.CODE from UTM5.users u join UTM5.accounts a on a.id = u.basic_account and a.is_deleted = 0 join userside.tbl_base tb on tb.CODETI = u.id where u.is_deleted = 0 and a.external_id = '".$vars['external_id']['val']."' ");
    if(!is_array($r)) return " нет данных по абоненту ".$vars['external_id']['val']." ";
    $u_uid = $r['CODE'];
    //$date_day = date("m.d.y");
    //$date_hour = date("H");
    //$date_minute = date("i");
    $comment=$vars['request_comment']['val'];
    $db->query("insert into userside.tbl_journal (STATUS,HOUSECODE,TYPER,USERCODE,DATEADD,DATEDO,OPIS,NEWKLIENT,FROMAPROVE,
    UZELCODE,PRIORITY,APART) values (1,0,".$request_type.",".$u_uid.",NOW(),NOW(),'".$comment."',0,0,0,0,0) ");
    //echo $db->query;
    

    $abon_otdel = 3;
    $call_center = 9;

    if( $request_type == 9 ) $call_center = 8;
            $r = $db->fetch_row_array( "select max(CODE) c from userside.tbl_journal" );
            $new_id = $r['c'];
            $sql = "insert into userside.tbl_journal_staff (JOURNALCODE, PERSCODE, ISPODRAZD ) values
            ( $new_id, $abon_otdel, 1 )
        , ( $new_id, $call_center, 1)";
            $db->query( $sql );
                                                                    
    
    return true;                              
    //return true;
}
    
function new_request_tt()
{
    global $db,$vars;
    
    #if($vars['request_type']['val']==1) {$request_type=10;} //покупка роутера
    #if($vars['request_type']['val']==2) {$request_type=9;}  //подключение тв
    
    $external_id=$vars['external_id']['val'];
    $type=$vars['request_type']['val'];
    $title=$vars['title']['val'];
    $comment=$vars['request_comment']['val'];
    
    #$r1 = $db->query("set character set utf8");
    #$ins1 = $db->query("INSERT INTO tt.issues (summary,problem,opened,modified,gid,status,opened_by,severity,product,private)     VALUES(".$title.",".$comment." ,unix_timestamp(now()),unix_timestamp(now()),'6','11','196','3','4','f')");
    $ins1= "INSERT INTO tt.issues (summary,problem,opened,modified,gid,status,opened_by,severity,product,private) VALUES('".$title."','".$comment."' ,unix_timestamp(now()),unix_timestamp(now()),'6','11','196','3','4','f')";
    
    $db->query( $ins1 );
    #$sel = $db->fetch_row_array("select max(issueid) as is_id from tt.issues");
    #$is_id = $sel['is_id'];
    #if(isset($is_id)) return "$ins1";
    #$ins2 = "INSERT INTO tt.issue_log (issueid,logged,userid,message,private) VALUES(".$is_id.",unix_timestamp(now()),'196','Заявка зарегистирована','f')";
    #$ins3 = "INSERT INTO tt.issue_groups (issueid,gid,opened) VALUES(".$is_id.",'6',unix_timestamp(now()))";
    #$db->query( $ins2 );
    #$db->query( $ins3 );
       
        
    return true;
    //return true;
}


function create_contract()
{
	global $db,$vars;
        global $utm5_user_id, $utm5_account_id;

	$firm = $vars['firm']['val'] ? $vars['firm']['val'] : "xxxxx";
	$ext_id = $vars['external_id']['val'];
	$is_jur = $vars['is_juridical']['val'];
    $contract_num = trim( ! $vars['contract_num']['val'] ? ( $is_jur ? get_next_contract_num( $firm, $is_jur ) : $ext_id ) : $vars['contract_num']['val'] );
	$full_name = trim( $vars['full_name']['val'] );
	$h_id = $vars['house_id']['val'];
    $actual_address = $vars['address_text']['val'];
    if( ! $actual_address )
    {
        $r_ad = $db->fetch_row_array( "select concat( street, ' д. ', number, building ) ad from houses where id = $h_id" );
        $actual_address = $r_ad['ad'];
    }
	$ent = $vars['entrance']['val'];
	$floor = $vars['floor']['val'];
	$flat_number = $vars['flat_number']['val'];
	$is_blocked = $vars['is_blocked']['val']; if( is_null( $is_blocked ) ) $is_blocked = 1;
	$balance = $vars['balance']['val']; if( is_null( $balance ) ) $balance = 0;
	$credit = $vars['credit']['val']; if( is_null( $credit ) ) $credit = 0;
	$int_status = $vars['internet_status']['val']; if( is_null( $int_status ) ) $int_status = 1;
	$passwd = gen_passwd('6');
    $contract_date = $vars['contract_date']['val'];
    $month_rus = array( 
        1 => 'января'
        , 2 => 'февраля'
        , 3 => 'марта'
        , 4 => 'апреля'
        , 5 => 'мая'
        , 6 => 'июня'
        , 7 => 'июля'
        , 8 => 'августа'
        , 9 => 'сентября'
        , 10 => 'октября'
        , 11 => 'ноября'
        , 12 => 'декабря'
        );
    $contract_date = date( "«d» " . $month_rus[ (int) date( "n", $contract_date ) ] . " Y г.", $contract_date );
    $jur_position = $vars['jur_position']['val'];
    $jur_person = $vars['jur_person']['val'];
    $jur_reason = $vars['jur_reason']['val'];
    $jur_address = $vars['jur_address']['val'];
    $jur_fax = $vars['jur_fax']['val'];
    $passport = 'Серия № Выдан:';
    $jur_inn = $vars['jur_inn']['val'];
    $jur_kpp = $vars['jur_kpp']['val'];
    $jur_korr = $vars['jur_korr']['val'];
    $jur_bik = $vars['jur_bik']['val'];
    $jur_account = $vars['jur_account']['val'];
    $contact_person  = $vars['contact_person']['val'];

	$ud = get_user_data_ids( $ext_id );
	if( is_array( $ud ) ) return "Contract with external_id=$ext_id already exists with user id " . $ud['uid'];
	// Создаем договор
	$cmd = UTM_CMD . "-a add_user -login \"$ext_id\" -password \"$passwd\" -full_name \"$full_name\" -house_id \"$h_id\" -act_address \"$actual_address\" -is_juridical \"$is_jur\" -entrance \"$ent\" -floor \"$floor\" -flat_number \"$flat_number\" -is_blocked \"$is_blocked\" -balance \"$balance\" -credit \"$credit\" -int_status \"$int_status\" -tax_number \"$jur_inn\" -kpp_number \"$jur_kpp\" -passport \"$passport\"  -bank_account \"$jur_account\"" . ( $contact_person ? " -comments \"Контактное лицо: $contact_person\"" : "" ) . ( $jur_address ? " -jur_address \"$jur_address\"" : "" );

        $xml_parser = xml_parser_create( 'UTF-8' );
        xml_parser_set_option( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
        xml_set_element_handler( $xml_parser, "startElement", "endElement" );
        error_log( "Executing '$cmd'" );
        if( ! ( $fp = popen( "$cmd 2>/dev/null", "r" ) ) ) return( "could not open XML input" );

        while( $data = fread( $fp, 4096 ) )
        {
            $rows = preg_split( "/\n/", $data );
            if( is_array( $rows ) )
                foreach( $rows as $row )
                    error_log( $row );
            else
                error_log( $data );
                if( ! xml_parse( $xml_parser, $data, feof( $fp ) ) ) 
			return ( sprintf( "XML error: %s at line %d"
				, xml_error_string( xml_get_error_code( $xml_parser ) )
				, xml_get_current_line_number( $xml_parser ) ) );
        }
        xml_parser_free( $xml_parser );
        pclose( $fp );

	if( ! $utm5_user_id ) return "Ошибка выполнения команды создания договора.";
    if( $is_jur )
    {
        $cmd = UTM_CMD . "-a edit_user -user_id $utm5_user_id -parameter_id 1 -parameter_value \"$contract_num\" -parameter_id 2 -parameter_value \"$firm\" -parameter_id 5 -parameter_value \"$contract_date\" -parameter_id 8 -parameter_value \"$jur_fax\" -parameter_id 9 -parameter_value \"$jur_korr\" -parameter_id 10 -parameter_value \"$jur_bik\" 2>&1";
        my_exec( $cmd );
        $cmd = UTM_CMD . "-a add_user_contact -user_id $utm5_user_id -descr \"$jur_position\" -id_exec_man 1 -person \"$jur_person\" -reason \"$jur_reason\" -short_name \"$jur_person\" -email \"\" -contact \"\" 2>&1";
        my_exec( $cmd );
    }
    else
    {

        // Добавление фирмы и номера договора
        $cmd = UTM_CMD . "-a edit_user -user_id $utm5_user_id -parameter_id 1 -parameter_value \"$contract_num\" -parameter_id 2 -parameter_value \"$firm\" 2>&1";
        my_exec( $cmd );

        // Добавляем услугу SMS оповещения
        $dp = $db->fetch_row_array( "select id from discount_periods where is_expired=0 and static_id=1" );
        $dp_id = $dp['id'];
        $sms_service = $firm == 'zzzzzzz' ? 333 : 780;
        $cmd = UTM_CMD." -a link_service -user_id $utm5_user_id -account_id $utm5_account_id -service_id $sms_service -discount_period_id $dp_id 2>&1";
    }

	$cmd = UTM_CMD . "-a set_account_external_id -aid \"$utm5_account_id\" -external_id \"$ext_id\" 2>&1";
	my_exec( $cmd );

	$cmd = UTM_CMD . "-a edit_account -account_id \"$utm5_account_id\" -is_blocked 1904 -block_recalc_abon 0 -block_recalc_prepaid 0 2>&1";
	my_exec( $cmd );

	return true;
}

function link_tariff()
{
	global $db,$vars;
	global $utm5_tariff_link_id;
    global $MA_utm_tariff_links, $tar_pcplayer, $tar_stb;
    $p_ids = $MA_utm_tariff_links;

	$ext_id = $vars['external_id']['val'];
	$t_id = $vars['tariff_id']['val'];
	$l = $vars['login']['val'];
    if( ! $l ) 
        $l = get_free_login( $ext_id, in_array( $t_id, $tar_stb ) ? 'tv' : '' );

	$ud = get_user_data_ids( $ext_id );
	if( ! $ud['uid'] ) return "Not found account with external_id=$ext_id.";
	$passwd = $ud['password'];

    $firm_row = $db->fetch_row_array( "select uap.value firm from users u join accounts a on ( a.id = u.basic_account and u.is_deleted = 0 and a.is_deleted = 0 ) join user_additional_params uap on ( uap.userid = u.id and uap.paramid = 2 ) where a.external_id = $ext_id" );
    $firm = $firm_row['firm'];

	$h = $db->fetch_row_array( "select house_id from users where id=\"" . $ud['uid'] . "\" limit 1" );
	$h_id = $h['house_id'];
	$ip = get_free_ip( $h_id, $firm );
	if( ! $ip ) return "Can't find ip for house_id $h_id";

	$dp = $db->fetch_row_array( "select id from discount_periods where is_expired=0 and static_id=1" );
	if( ! $dp ) return "Can't find actual discount period";
	$dp_id = $dp['id'];

    // Ищем первую попавшуюся привязку для любого ip на этом договоре
    $link_data = array();
    $sql = "select inet_ntoa( ig.ip & 0xffffffff ) ip from accounts a join service_links sl on sl.account_id = a.id and sl.is_deleted = 0 join iptraffic_service_links ipsl on ipsl.id = sl.id and ipsl.is_deleted = 0 join ip_groups ig on ig.ip_group_id = ipsl.ip_group_id and ig.is_deleted = 0 where a.is_deleted = 0 and a.external_id = '$ext_id'";
    $q = $db->query( $sql );
    while( $r = $db->fetch_array( $q ) )
        if( $link_data = ldev_get_ip_link_data( $r[ 'ip' ] ) )
            break;

    // Добавление ТВ
	$stb_id = 0;
	$stb_pass = 0;
	$stb_type = 0;
	if( in_array( $t_id, $tar_stb ) || in_array( $t_id, $tar_pcplayer ) ) 
	{ // Добавляется услуга ТВ - создаем в МА
        global $MA_url, $MA_key, $MA_net_id;
        $url = $MA_url;
        $key = $MA_key;
        $net_id = $MA_net_id;

		$u_data = MA_get_user_data( $ext_id );

		preg_match( "/(\S+)\s+(\S+)\s*(.*)/", trim( $u_data['fn'] ), $u_name );
		$ln = trim( $u_name[1] );
		$fn = trim( $u_name[2] );
		$mn = trim( $u_name[3] );
        if( ! $mn ) $mn = "нет";

		$ad = $u_data['ad'];
		$ps = $u_data['ps']; if( ! $ps ) $ps = "Неизвестно";
		$ph = $u_data['p1'] . ", " . $u_data['p2'] . ", " . $u_data['p3'];
		$res = MA_add_user( $url, $net_id, $key, $ext_id, $ln, $fn, $mn, $ad, $ps, $ph );

		$stb_id = $net_id . $u_data['id'] . rand( 0, 9 );
		$stb_pass = MA_gen_pass( 8 );
		$stb_type = 'NV101';
		$res = MA_add_stb( $url, $net_id, $key, $ext_id, $stb_id, $stb_pass, $stb_type );
		if( ! ( is_int( $res ) && $res == 0 ) )
		{
			$stb_id = 0;
			$stb_pass = 0;
			$stb_type = 0;
		}

		$res = MA_add_pack( $url, $net_id, $key, $ext_id, $stb_id, $p_ids[ $t_id ] );
	}

	var_set( 'stb_id', $stb_id );
	var_set( 'stb_pass', $stb_pass );
	var_set( 'stb_type', $stb_type );

	$qq = $db->query( "select id, service_type st, parent_service_id psid, c.count ipsc from services_data sd join ( select count(1) count from services_data where tariff_id = '$t_id' and service_type = 3 and is_deleted = 0 ) c where tariff_id = '$t_id' and link_by_default  = 1 and is_deleted = 0" );
	while( $rr = $db->fetch_array( $qq ) )
		$serv[] = $rr;
	// Если количество услуг подключаемых по умолчанию типа IP traffic больше 1, то добавляем сначала только тариф а потом все услуги к нему
	if( $serv[0]['ipsc'] > 1 )
	{
		// Добавляем тариф
		$cmd = UTM_CMD . "-a link_tariff -user_id \"" . $ud['uid'] . "\" -account_id \"" . $ud['basic_account'] . "\"  -discount_period_id \"$dp_id\"  -tariff_current \"$t_id\"";
		
		$xml_parser = xml_parser_create( 'UTF-8' );
		xml_parser_set_option( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
		xml_set_element_handler( $xml_parser, "startElement", "endElement" );
        error_log( "Executing '$cmd'" );
		if( ! ( $fp = popen( "$cmd 2>/dev/null", "r" ) ) ) return( "could not open XML input" );

		while( $data = fread( $fp, 4096 ) )
		{
            $rows = preg_split( "/\n/", $data );
            if( is_array( $rows ) )
                foreach( $rows as $row )
                    error_log( $row );
            else
                error_log( $data );
			if( ! xml_parse( $xml_parser, $data, feof( $fp ) ) ) 
				return ( sprintf( "XML error: %s at line %d"
					, xml_error_string( xml_get_error_code( $xml_parser ) )
					, xml_get_current_line_number( $xml_parser ) ) );
		}
		xml_parser_free( $xml_parser );
		pclose( $fp );
        $tl_id = $utm5_tariff_link_id;

		// Добавляем услуги
		foreach( $serv as $s )
		{
			$c1 = "";
			// Если добавляем услугу типа IP traffic
			if( $s['st'] == 3 )
			{
				$ip1 = get_free_ip( $h_id, $firm );
				if( ! $ip1 ) return "Can't find ip for house_id $h_id";
				if( $s['psid'] == 614 ) // Если это IPtv 
					$l1 = get_free_login( $ext_id, 'tv'); 
                elseif( $psid == 756 ) // Если это Телефония 
					$l1 = get_free_login( $ext_id, 'pv'); 
 
				else
					$l1 = get_free_login( $ext_id, '' );
				$c1 = "-iptraffic_login \"$l1\" -iptraffic_password \"$passwd\" -dont_use_fw 0 -unprepay 0 -ip_address \"$ip1\"" . ( ( $stb_id && ( $s['psid'] == 614 ) ) ? " -mac $stb_id " : "" );
			}
			$cmd = UTM_CMD . " -a link_service -user_id \"" . $ud['uid'] . "\" -account_id \"" . $ud['basic_account'] . "\" -service_id \"{$s['id']}\" -unabon 1 -discount_period_id \"$dp_id\" " . ( $tl_id ? " -tariff_link_id $tl_id " : ""  ) . " $c1 2>&1";
			my_exec( $cmd );
            // если IP услуга и найдена привязка - привязываем туда же
            if( $c1 && is_array( $link_data ) && $link_data[ 'port_id' ] )
                ldev_add_user_link( $link_data[ 'ldev_id'], $link_data[ 'port_id'], $ip1, $l1, $ext_id );
		}
	}
	else
	{
		$cmd = UTM_CMD . "-a link_tariff_with_services -user_id \"" . $ud['uid'] . "\" -account_id \"" . $ud['basic_account'] . "\"  -discount_period_id \"$dp_id\"  -tariff_current \"$t_id\" -iptraffic_login \"$l\" -iptraffic_password \"$passwd\" -dont_use_fw 0 -unabon 1 -unprepay 0 -ip_address \"$ip\"" . ( $stb_id ? " -mac $stb_id " : "" );
		
		$xml_parser = xml_parser_create( 'UTF-8' );
		xml_parser_set_option( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
		xml_set_element_handler( $xml_parser, "startElement", "endElement" );
        error_log( "Executing '$cmd'" );
		if( ! ( $fp = popen( "$cmd 2>/dev/null", "r" ) ) ) return( "could not open XML input" );

		while( $data = fread( $fp, 4096 ) )
		{
            $rows = preg_split( "/\n/", $data );
            if( is_array( $rows ) )
                foreach( $rows as $row )
                    error_log( $row );
            else
                error_log( $data );
			if( ! xml_parse( $xml_parser, $data, feof( $fp ) ) ) 
				return ( sprintf( "XML error: %s at line %d"
					, xml_error_string( xml_get_error_code( $xml_parser ) )
					, xml_get_current_line_number( $xml_parser ) ) );
		}
		xml_parser_free( $xml_parser );
		pclose( $fp );
        // если найдена привязка - привязываем туда же
        if( is_array( $link_data ) && $link_data[ 'port_id' ] )
            ldev_add_user_link( $link_data[ 'ldev_id'], $link_data[ 'port_id'], $ip, $l, $ext_id );
	}

	foreach( $serv as $s )
		if( $s['psid'] == 814 ) // Если drweb - создаем
		{ // Код спизжен из функции link_service
			// начинаем обрабатывать запрос для Дрвэб

			function httpsPost($Url, $strRequest)
			{
				// Initialisation
				$ch=curl_init();
				// Set parameters
				curl_setopt($ch, CURLOPT_URL, $Url);
				// Return a variable instead of posting it directly
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				// Active the POST method
				curl_setopt($ch, CURLOPT_POST, 1) ;
				//auth
				curl_setopt($ch, CURLOPT_USERPWD, "zzzzz:xxxxxxxxxx2");
				// Request
				curl_setopt($ch, CURLOPT_POSTFIELDS, $strRequest);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				// execute the connexion
				$result = curl_exec($ch);
				// Close it
				curl_close($ch);
				return $result;
			}
			$service_id = $s['id'];
			if( $service_id == 815 ) // Тариф "Все подключено"
				$rate='2888b7ff-3625-465e-bcb8-957de17f6458';
			else // По дефолту подключаем Классик (69 р.)
				$rate='2888b7ff-3625-465e-bcb8-957de17f6458';

			//генерируем дату для  slink и для ссылки (для связи по ex_id s_id $startdate)
			$timestamp = time();
			$date_time_array = getdate($timestamp);
			$timestamp = mktime($date_time_array['hours'],$date_time_array['minutes'],$date_time_array['seconds'],
			$date_time_array['mon'],$date_time_array['mday'],$date_time_array['year']  );
			$h=$date_time_array['hours']; $m=$date_time_array['minutes']; $s=$date_time_array['seconds'];
			$M=$date_time_array['mon'];$D=$date_time_array['mday'];$Y=$date_time_array['year'];

			$startdate1=$timestamp;
			$startdate2=("$Y$M$D$h$m$s");

			$external_id=$ext_id;
			$account_id=$ud['basic_account'];
			$user_id=$ud['uid'];
			$pass='123';
			$expires=0;
			$link="http://xxx.xxx/download/download.ds?id=".$external_id."_".$startdate1." ";
			$url ="http://xxxxxxx.ru:908/api/3.0/stations/add.ds?id=".$external_id."_".$startdate1."&name=".$external_id."&password=".$pass."&rate=".$rate."&expires=".$expires." ";

			$db->query("insert into drweb_data values ('',".$external_id.",'".$pass."','".$account_id."','".$user_id."','".$service_id."','".$rate."','".$link."','".$startdate1."',0,0) ");

            error_log( "Executing '$url'" );
            $Response = httpsPost( $url, '' );
            error_log( "Result: '$Response'" );
			//закончили дрвэб    
		}
	if( ! $utm5_tariff_link_id ) return "Ошибка добавления тарифа";
	var_set( 'tlink_id', $utm5_tariff_link_id );
	

	return true;
}

function link_service_tariff()
{
	global $db,$vars;

	$ext_id = $vars['external_id']['val'];
	$t_id = $vars['tlink_id']['val'];
	$s_id = $vars['service_id']['val'];
	$stb_id = $vars['stb_id']['val'];

	$ud = get_user_data_ids( $ext_id );
	if( ! $ud['uid'] ) return "Not found account with external_id=$ext_id.";

	$dp = $db->fetch_row_array( "select id from discount_periods where is_expired=0 and static_id=1" );
	if( ! $dp ) return "Can't find actual discount period";
	$dp_id = $dp['id'];

	$cmd = UTM_CMD . " -a link_service -user_id \"" . $ud['uid'] . "\" -account_id \"" . $ud['basic_account'] . "\" -service_id \"$s_id\" -discount_period_id \"$dp_id\" -tariff_link_id $t_id -unabon 1 2>&1";
	my_exec( $cmd );

	
	$serv_stb = array( 
              // xxxxx смотреть   xxxxx выбор     xxxxx по полной
                 "743" => "77", "745" => "77", "746" => "77" // xxxxx HD
			   , "747" => "76", "748" => "76", "749" => "76" // xxxxx спорт
			   , "750" => "78", "751" => "78", "752" => "78" // xxxxx себя
			   , "753" => "13", "754" => "13", "755" => "13" // xxxxx наш футбол

            //        HD           Спортивный         Кино      Развлекательный     Футбол           Эротика    Познавательный     Детям
			   , "822" => "104", "826" => "103", "827" => "105", "828" => "106", "829" => "110", "830" => "109", "831" => "108", "833" => "107" // Кино на большом экране
               , '875' => '104', '876' => '103', '877' => '105', '878' => '106', '879' => '110', '880' => '109', '881' => '108', '882' => '107' // Основной
               , '956' => '104', '957' => '103', '958' => '105', '959' => '106', '960' => '110', '962' => '109', '961' => '108', '955' => '107' // 60 рублей за 60 каналов
               //    Спорт            HD         Наш футбол    Для взрослых
               , '915' => '76', '916' => '77', '917' => '13', '918' => '78' // Интернет + кино расширенный
               , '911' => '76', '912' => '77', '913' => '13', '914' => '78' // Интернет + кино
               , '672' => '76', '678' => '77', '685' => '13', '691' => '78' // ТВ Расширенный STB
               , '671' => '76', '677' => '77', '686' => '13', '692' => '78' // ТВ Стандартный STB
               , '670' => '76', '676' => '77', '687' => '13', '693' => '78' // ТВ Минимальный STB
               , '675' => '76', '681' => '77', '682' => '13', '688' => '78' // ТВ Расширенный PCPlayer
               , '674' => '76', '680' => '77', '683' => '13', '689' => '78' // ТВ Стандартный PCPlayer
               , '673' => '76', '679' => '77', '684' => '13', '690' => '78' // ТВ Минимальный PCPlayer
			  );
	if( $stb_id && $serv_stb[ $s_id ] )
	{ // Добавляем пакет в МА
        global $MA_url, $MA_key, $MA_net_id;
        $url = $MA_url;
        $key = $MA_key;
        $net_id = $MA_net_id;

		$res = MA_add_pack( $url, $net_id, $key, $ext_id, $stb_id, $serv_stb[ $s_id ] );
	}

	
	return true;
}

function buy_device()
{
	global $db,$vars;

	$ext_id = $vars['external_id']['val'];
	$summ = - abs( $vars['summ']['val'] );
	$comment = $vars['device_name']['val'];

	$ud = get_user_data_ids( $ext_id );
	if( ! $ud['uid'] ) return "Not found account with external_id=$ext_id.";

	$cmd = UTM_CMD." -a add_payment -account_id \"" . $ud['basic_account'] . "\" -payment \"$summ\" -currency_id 810 -payment_method 102 -turn_on_inet 0 -comment \"$comment\" -admin_comment \"$comment\" 2>&1";
	
	my_exec( $cmd );
	return true;
}

function create_unlimit_internet_tariff()
{
    global $db,$vars,$utm5_tariff_id;
    $tarif_group_id = 9;
    $cost = $vars['cost']['val'];
    $speed = $vars['speed']['val'];
    $is_juridical = $vars['is_juridical']['val'];
    $firm = $vars['firm']['val'];
    $firm_id = $db->fetch_row_array( "select id from lru_firms where is_deleted = 0 and name = '$firm'" );

    $sp = $speed * 1000;
    $sql = "select distinct lt.tarif_id id from lru_tarif lt join lru_unlim1 lu using( tarif_id ) join services_data sd on sd.tariff_id = lt.tarif_id and sd.is_deleted = 0 join periodic_services_data psd on psd.id = sd.id where lt.tarif_group_id = 9 and lt.is_juridical = " . ( $is_juridical ? "1" : "0" ) . " and lt.status = 0 and lt.firm_id = {$firm_id[id]} and lu.boi1 = $sp and lu.boo1 = $sp and lu.boi2 = $sp and lu.boo2 = $sp and psd.cost = $cost";
    $tariff = $db->fetch_row_array( $sql );
    if( $tariff['id'] )
    {
        var_set( 'tariff_id', $tariff['id'] );
        return true;
    }

    $name = "$firm " . ( $is_juridical ? " ЮР" : "" ) . " $speed Мбит за $cost р.";
    $cmd = UTM_CMD . " -a add_tariff_iptraffic -name '$name' -cost $cost";
    $xml_parser = xml_parser_create( 'UTF-8' );
    xml_parser_set_option( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_set_element_handler( $xml_parser, "startElement", "endElement" );
    error_log( "Executing '$cmd'" );
    if( ! ( $fp = popen( "$cmd 2>/dev/null", "r" ) ) ) return( "could not open XML input" );

    while( $data = fread( $fp, 4096 ) )
    {
        $rows = preg_split( "/\n/", $data );
        if( is_array( $rows ) )
            foreach( $rows as $row )
                error_log( $row );
        else
            error_log( $data );
        if( ! xml_parse( $xml_parser, $data, feof( $fp ) ) ) 
            return ( sprintf( "XML error: %s at line %d"
                , xml_error_string( xml_get_error_code( $xml_parser ) )
                , xml_get_current_line_number( $xml_parser ) ) );
    }
    xml_parser_free( $xml_parser );
    pclose( $fp );
    $sql = "insert into lru_tarif(tarif_id, type, tarif_type, comm, status, tarif_group_id, firm_id, is_juridical, periodic_type) values ( $utm5_tariff_id, 'акция', -1, 'Автоматически созданный тариф фирмы \"$firm\" скорость $speed Мбит абонплата $cost руб.', 0, $tarif_group_id, " . $firm_id['id'] . ", " . ( $is_juridical ? "1" : "0" ) . ", 'month' )";
    $db->query( $sql );
    $speed *= 1000;
    $sql = "insert into lru_unlim1(tarif_id, bytes, ho_s, ho_e, boi1, boo1, boi2, boo2) values( $utm5_tariff_id, -1, 0, 24, $speed, $speed, $speed, $speed )";
    $db->query( $sql );
    $sql = "insert into tt.tlc_geo_groups_tariffs( group_id, tarif_id ) select group_id, $utm5_tariff_id from tt.tlc_geo_groups_firms where firm_id = " . $firm_id['id'];
    $db->query( $sql );
    var_set( 'tariff_id', $utm5_tariff_id );
    return true;
}

function set_promised_payment_for_all_active()
{
    global $db,$vars;
    return false;
    $q = $db->query( 'select id,balance from accounts where is_deleted = 0 and is_blocked = 0 and credit < 100' );
    while( $r = $db->fetch_array( $q ) )
    {
        $account_id = $r['id'];
        $balance = $r['balance']+0;
        $report[$account_id]['balance'] = $balance;
        //выбираем переодические стоимости
        $sum1=0;
        $r1 = $db->fetch_row_array("select ifnull(sum(psd.cost),0) as s from service_links sl, services_data sd, periodic_services_data psd where 
        sl.service_id=sd.id and sd.id=psd.id and sd.is_deleted=0 and sl.is_deleted=0 and psd.is_deleted=0 and sl.account_id=".$account_id." ");
        $sum1= $r1['s']; 
        //выбираем разовые стоимости
        $sum2=0;
        $r2 = $db->fetch_row_array("select ifnull(sum(osd.cost)*3,0) as s
        from once_service_data osd, tariffs_services_link tsl, account_tariff_link atl , services_data sd
        where atl.tariff_id=tsl.tariff_id and tsl.service_id=osd.id and osd.id=sd.id and osd.is_deleted=0 and atl.is_deleted=0 and tsl.is_deleted=0 
        and sd.link_by_default=0 and sd.service_type=1	and atl.account_id=".$account_id." ");
        $sum2= $r2['s'];
        $sum= (($sum1+$sum2)*2);
        $report[$account_id]['sum'] = $sum1+$sum2;
        if($balance > ($sum1+$sum2) ) { $report[$account_id]['message']="На балансе достаточно средств"; continue; }
        $burn_date=mktime(10,0,0,1,9,2014);

        $cmd = UTM_CMD." -a add_credit_payment -account_id $account_id -payment $sum -currency_id 810 -burn_date $burn_date -payment_method 7 -turn_on_inet 1 2>&1";
        $report[$account_id]['cmd'] = $cmd;
        my_exec( $cmd );
    }
    var_dump( $report );
    var_set( 'report', 0 );
    return true;
}


function send_add_friend_message()
{
    global $db,$vars;

/*    $r = $db->fetch_row_array("
    SELECT u.password as p FROM users u, accounts a where a.external_id='".$vars['login']['val']."' and a.is_deleted=0
    and u.is_deleted=0 and a.id=u.basic_account
    #and a.external_id like '21%'
    ");
    if(!is_array($r)) return "Не найден логин ".$vars['login']['val']." ".$r['p'];
*/

//$r = $db->fetch_row_array("SELECT email FROM users u where login='".$vars['login']['val']."' AND is_deleted=0  and email!=''");
//if(!is_array($r)) return "Для логина ".$vars['login']['val']." не указан почтовый ящик";

    $external_id=$vars['external_id']['val'];
    $full_name = $vars['full_name']['val'];
    $friend_2_name = $vars['friend_2_name']['val'];
    $email = $vars['email']['val'];

//$img1='/logo.jpg';
    $message_body = "";

$to  = "$email" ; //основная строка
//$to ="m@xxxxxxx.ru"; //тестовая строка
$subject = "Ваш друг $full_name  ";
$subject = '=?koi8-r?B?'.base64_encode(iconv ("utf-8","koi8-r",$subject)).'?=';
$message = "$message_body";

$headers  = "Content-type: text/html; charset=utf-8 \r\n";

$headers .= "From: =?koi8-r?B?" . base64_encode( iconv( "utf-8", "koi8-r", 'zzzzzzz' ) ) . "?= <noreply@xxxxxxx.ru>\r\n";
#$headers .= "Bcc: birthday-archive@example.com\r\n";


mail($to, $subject, $message, $headers);


  return true;
  //return $url;
}




function add_friends()
{
   global $db, $vars;
   $friend_1 = $vars['friend_1']['val'];
   $friend_2 = $vars['friend_2']['val'];
   $firm = $vars['firm']['val'];
   $summ = $vars['summ']['val'];

   $sql = "select count(1) c from users u join accounts a on u.basic_account = a.id and a.is_deleted = 0 join user_additional_params uap on uap.userid = u.id and uap.paramid = 2 where u.is_deleted = 0 and uap.value = '$firm' and a.external_id = '$friend_1'";
   $r = $db->fetch_row_array( $sql );
   if( $r['c'] < 1 )
       return "Unknown friend_1: '$friend_1' in firm '$firm'";
   if( $r['c'] > 1 )
       return "More than one friend_1: '$friend_1' in firm '$firm'";

   $sql = "select count(1) c from users u join accounts a on u.basic_account = a.id and a.is_deleted = 0 join user_additional_params uap on uap.userid = u.id and uap.paramid = 2 where u.is_deleted = 0 and uap.value = '$firm' and a.external_id = '$friend_2'";
   $r = $db->fetch_row_array( $sql );
   if( $r['c'] < 1 )
       return "Unknown friend_2: '$friend_2' in firm '$firm'";
   if( $r['c'] > 1 )
       return "More than one friend_2: '$friend_2' in firm '$firm'";
   
   if ($firm='zzzzzzz') { ;$firm='xxxxxxx'; } else {$firm='xxxxxxx';}

   $sql = "select friend_1 from friends where friend_2 = '$friend_2'";
   $r = $db->fetch_row_array( $sql );
   if( $r['friend_1'] )
       return "friend_2 $friend_2 already have friend_1 $friend_1.";
       
   $sql = "select friend_1 from friends where friend_1 = '$friend_2'";
   $r = $db->fetch_row_array( $sql );
   if( $r['friend_1'] )
   return "friend_2 $friend_2 already in database in field friend_1 (on uje privodil druga i ego nelzya privesti)";
                    
   
   $sql="insert into friends (friend_1,friend_2,firm,add_date,status,activation_date,summ) 
   values ('".$friend_1."','".$friend_2."','".$firm."',unix_timestamp(now()),'0','0','".$summ."')";
   
   $db->query( $sql );
      
   return true;
}




function activate_friends()
{
    global $db, $vars;
    
    //$pid = !$vars['geo_id']['val'] ? "IS NULL" : "= ".$vars['geo_id']['val'];
    
    //$external_id = !$vars['external_id']['val'] ? "IS NULL" : "and a.external_id= ".$vars['external_id']['val'];
    	// по всем
	$q = $db->query("select f.id, f.friend_1, f.friend_2, f.summ from friends f, accounts a
	where f.friend_2=a.external_id and a.is_blocked=0 and f.status=0  ".$external_id."");
//echo $q;
//echo $external_id;
//	$external_id = $vars['external_id']['val'];
//	//по юзеру
//	$q = $db->query("select id, friend_1, friend_2, summ from friends where friend_2=".$external_id." and status=0 ");
	
    $sql = "select count(*) as count from friends f, accounts a
            where f.friend_2=a.external_id and a.is_blocked=0 and f.status=0  ".$external_id."";
    $c = $db->fetch_row_array( $sql );
    if( $c['count'] )
    {
    var_set( 'count', $c['count'] );
    }
                                    
    while( $r = $db->fetch_array( $q ) )
	{
    	//if(!is_array($r)) return "нет такого абонента";
    	
    	$friend_1 = $r['friend_1'];
    	$r1 = $db->fetch_row_array("select id from accounts where external_id=".$friend_1." ");
    	$account_id_1= $r1['id'];
    	
    	$friend_2 = $r['friend_2'];
    	$r2 = $db->fetch_row_array("select id from accounts where external_id=".$friend_2." ");
    	$account_id_2= $r2['id'];
    	
    	$id = $r['id'];
    	$summ = $r['summ'];	
	
	//echo $account_id_1;
	
	
	$cmd = UTM_CMD." -a add_payment -account_id ".$account_id_1." -payment ".$summ." -payment_method 121 -turn_on_inet 1 2>&1 ";
        echo "$cmd </br>";
	my_exec( $cmd );
	$cmd = UTM_CMD." -a add_payment -account_id ".$account_id_2." -payment ".$summ." -payment_method 121 -turn_on_inet 1 2>&1 ";
	echo "$cmd </br>";
	my_exec( $cmd );    
    	$sql1="update friends set status=1, activation_date =unix_timestamp(now()) where id=".$id." ";
	echo "$sql1 </br>";
	$db->query($sql1);
	return true;
	}
    if( $c['count'] )
        {
    var_set( 'count', $c['count'] );
        }
                	
    return true;
}


function set_credit()
{
    global $db, $vars;
    $external_id = $vars['external_id']['val'];
    $summ = $vars['summ']['val'];
    $date_to = $vars['date_to']['val'];
    $comment = $vars['comment']['val'];

    if( ! ( ( $summ > 0 ) || ( $summ == 0 && $date_to == 0 ) ) )
        return "Некорректная сумма.";
    if( $date_to <= time() && $date_to != 0 )
        return "Дата окончания кредита уже прошла.";

	$ud = get_user_data_ids( $external_id );
    $account_id = $ud['basic_account'];
    $cur_credit = $ud['credit'];

    // Отменяем все не закрытые временные кредиты
    $t_credit = 0;
    $cancel_credits = array();
    $last_payment_arch_table = $db->fetch_row_array( "select table_name from archives where table_type = 7 order by id desc limit 1" );
    $last_payment_arch_table = $last_payment_arch_table[ 'table_name' ];
    $q = $db->query( "select a.id, c.payment_trans_id, c.start_date, c.expire_date, c.value from credits c join accounts a on a.id = c.account_id join payment_transactions p on p.id = c.payment_trans_id left join payment_transactions p1 on p1.payment_ext_number = p.id where status = 0 and is_passed = 0 and p.comments_for_admins not like '%Отмена кредита id%' and p1.id is null and a.external_id = $external_id
    union all 
    select a.id, c.payment_trans_id, c.start_date, c.expire_date, c.value from credits c join accounts a on a.id = c.account_id join $last_payment_arch_table p on p.id = c.payment_trans_id left join payment_transactions p1 on p1.payment_ext_number = p.id left join $last_payment_arch_table p2 on p2.payment_ext_number = p.id where status = 0 and is_passed = 0 and p.comments_for_admins not like '%Отмена кредита id%' and p1.id is null and p2.id is null and a.external_id = $external_id" );
    while( $r = $db->fetch_array( $q ) )
    {
        $c_id = $r['payment_trans_id'];
        $c_account_id = $r['id'];
        $c_sum = -$r['value'];
        $c_burn = $r['expire_date'] - 1;
        $c_comment = "Отмена кредита id $c_id от " . date( 'd-m-Y H:i:s', $r['start_date'] );
        $cmd = UTM_CMD . " -a add_credit_payment -account_id $c_account_id -payment $c_sum -currency_id 810 -burn_date $c_burn -payment_method 7 -turn_on_inet 1 -admin_comment '$c_comment' -comment '$c_comment' -payment_ext_number $c_id 2>&1";
        $cancel_credits[] = $cmd;
        $t_credit += -$c_sum;
    }

    if( $cur_credit != $t_credit )
    {
        $cmd = UTM_CMD . " -a edit_account -account_id $account_id -credit $t_credit 2>&1";
        my_exec( $cmd );
    }

    foreach( $cancel_credits as $cmd )
    {
        my_exec( $cmd );
    }
    
    if( $date_to == 0 )
    {
        $cmd = UTM_CMD . " -a edit_account -account_id $account_id -credit $summ -is_blocked 0 -int_status 1 2>&1";
        my_exec( $cmd );
        return true;
    }


    $cmd = UTM_CMD . " -a add_credit_payment -account_id $account_id -payment $summ -currency_id 810 -burn_date $date_to -payment_method 7 -turn_on_inet 1 -comment '$comment' -admin_comment '$comment' 2>&1";
    my_exec( $cmd );
    return true;
}

function unlink_tariff()
{
    global $db,$vars;
    $external_id = $vars['external_id']['val'];
    $tlink_id = $vars['tlink_id']['val'];
    $is_instant = $vars['is_instant']['val'];
    $is_refund = $vars['is_refund']['val'];

    $ud = get_user_data_ids( $external_id);
    if(!is_array($ud)) return "Not found account with external_id=$external_id";

    $account_id = $ud['account_id'];
    $user_id = $ud['uid'];
                
    $r = $db->fetch_row_array("SELECT tariff_id FROM account_tariff_link WHERE account_id='$account_id' and id='$tlink_id' AND is_deleted=0");
	if(!is_array($r)) return "Wrong tlink_id.";
    $tariff_id = $r['tariff_id'];


    if( $is_instant )
    {
        if( $is_refund )
        {
            $sql = "
    select
        t.id tariff_id
        , t.name tariff_name
        , sum(cost) cost
        , round( ceil( sum(cost)/( dp.end_date - dp.start_date ) * ( dp.end_date - unix_timestamp( curdate() ) ) * 100 ) / 100, 2 ) refund 
    from 
        service_links sl 
        join periodic_services_data psd on psd.id = sl.service_id 
        join periodic_service_links psl on psl.id = sl.id 
        join discount_periods dp on dp.id = psl.discount_period_id 
        join account_tariff_link atl on atl.id = sl.tariff_link_id
        join tariffs t on t.id = atl.tariff_id
    where 
        sl.is_deleted = 0 
        and psl.discounted_in_curr_period > 0 
        and sl.tariff_link_id = '$tlink_id'
            ";
            $r = $db->fetch_row_array( $sql );
            $cost = $r['cost'];
            $refund = $r['refund'];
            $tariff = $r['tariff_name'];
            $comment = "Перерасчёт по отключению тарифа $tariff";
            $cmd = UTM_CMD." -a add_payment -account_id \"$account_id\" -payment \"$refund\" -currency_id 810 -payment_method 102 -turn_on_inet 0 -comment \"$comment\" -admin_comment \"$comment\" 2>&1";
            if( $refund > 0 )
                my_exec( $cmd );
        }
        // Отвязываем IP и удаляем STB
        global $MA_url, $MA_key, $MA_net_id;
        $url = $MA_url;
        $key = $MA_key;
        $net_id = $MA_net_id;
        $sql = "
select
    inet_ntoa( ig.ip&0xffffffff ) ip
    , ig.mac mac 
from 
    service_links sl 
    join iptraffic_service_links ipsl using(id) 
    join ip_groups ig using( ip_group_id ) 
where  
    sl.is_deleted = 0 
    and ipsl.is_deleted = 0 
    and ig.is_deleted = 0 
    and sl.tariff_link_id = $tlink_id";
        $q = $db->query( $sql );
        while( $r = $db->fetch_array( $q ) )
        {
            $ip = $r['ip'];
            $stb_id = $r['mac'];
            if( $stb_id + 0 > $net_id + 0 )
                MA_del_stb( $url, $net_id, $key, $external_id, $stb_id );
            $sql = "SELECT lnk.id, lnk.ldev_id, lnk.port_id, lnk.port_access_id, lgk.ip ldev_ip
                    FROM tt.tlc_nobj_links lnk
                         join tt.tlc_nobj_logical lgk using( ldev_id )
                    WHERE port_ip='$ip'";
            $rr = $db->fetch_row_array( $sql );
            $ldev_id = $rr['ldev_id']+0;
            $port_id = $rr['port_id']+0;
            $link_id = $rr['id']+0;
            $links_count = $db->fetch_row_array( "SELECT COUNT(id) c FROM tt.tlc_nobj_links WHERE ldev_id = $ldev_id AND port_id = $port_id AND port_link_id != -1" );
            if ($links_count['c'] > 1)
               $db->query( "DELETE FROM tt.tlc_nobj_links WHERE id = '$link_id'" );
            elseif( $links_count['c'] == 1 )
                $db->query( "update tt.tlc_nobj_links set port_link_id = -1, port_access_id = -1, port_ip = -1, port_username = NULL, port_vlan_id = -1, port_provider_id = -1, last_update = unix_timestamp(), status = 0 WHERE id = '$link_id'" );
            if( $links_count['c'] > 0 ) 
                $db->query( "UPDATE tt.zz_dhcpservers SET ds_update_req=1 WHERE ds_update_req=0" );
        }
        $cmd = UTM_CMD." -a unlink_tariff -user_id $user_id -account_id $account_id -tariff_link_id $tlink_id 2>&1";
        my_exec( $cmd );
    }
    else
    {
        // Добавляем переход на тариф-заглушку для последующей обработки скриптом после начала следующего расчетного периода
        $new_tariff_id = 674; 
        $dp = $db->fetch_row_array( "select id from discount_periods where is_expired=0 and static_id=1" );
        if( ! $dp ) return "Can't find actual discount period";
        $dp_id = $dp['id'];
        $cmd = UTM_CMD."-a change_user_next_tariff -user_id $user_id -account_id $account_id -tariff_current $tariff_id -tariff_next $new_tariff_id -discount_period_id $dp_id -tariff_link_id $tlink_id 2>&1";
        my_exec( $cmd );
    }

    var_set( 'cost', $cost );
    var_set( 'refund', $refund );

    return true;
}

function parse_mailbox()
{
    global $db,$vars;

    $hostname = '{imap.yandex.ru:993/imap/ssl}INBOX';
    $username = 'xxxxxxxxxx@xxxxxxx.ru';
    $password = 'xxxxxxxx';

    $inbox = imap_open($hostname,$username,$password, OP_DEBUG) or die('Cannot connect to server: ' . imap_last_error());

    $emails = imap_search($inbox,'UNSEEN');

    if(is_array( $emails ) )
    {
        rsort($emails);
        foreach($emails as $email_number)
        {
            $overview = imap_fetch_overview( $inbox, $email_number, 0);
            if( ! is_array( $overview ) )
                continue;
            $overview = $overview[0];
            $message = imap_fetchbody( $inbox, $email_number, 1 );
            imap_clearflag_full ( $inbox , $email_number, '\\Seen' );
            $from = "";
            $subject = "";
            foreach( imap_mime_header_decode( $overview->from ) as $part )
                $from .= $part->charset == 'default' ? $part->text : iconv( $part->charset, 'UTF-8', $part->text );
            foreach( imap_mime_header_decode( $overview->subject ) as $part )
                $subject .= $part->charset == 'default' ? $part->text : iconv( $part->charset, 'UTF-8', $part->text );
            echo "<pre>";
            var_dump( $overview );
            echo "</pre>";
            echo "<br>-----------------------MESSAGE-----------------------------------------------<br>";
            printf( "from: %s<br>subject: %s<br><br>", $from, $subject );
            echo "$message<br>-----------------------------------------------------------------------------<br>";
        }
    }

    imap_close($inbox);

    return true;
}

function add_real_ip()
{
    global $db, $vars;

    $external_id = $vars['external_id']['val'];
    $current_ip = $vars['current_ip']['val'];
    $current_ip_c = ip2long( $current_ip );
    if( ! $current_ip_c )
        return "Bad ip $current_ip";

    $ud = get_user_data_ids( $external_id );
    if( ! is_array( $ud ) ) 
        return "Not found account with external_id=$external_id";

    $account_id = $ud['account_id'];
    $user_id = $ud['uid'];
	$passwd = $ud['password'];

    $sql = "
select 
    sl.id slink_id
    , sl.service_id
    , sl.tariff_link_id
    , psl.discount_period_id
from 
    service_links sl
    join iptraffic_service_links ipsl on ipsl.id = sl.id and ipsl.is_deleted = 0
    join periodic_service_links psl on psl.id = sl.id and psl.is_deleted = 0
    join ip_groups ig on ig.ip_group_id = ipsl.ip_group_id and ig.is_deleted = 0
where 
    sl.is_deleted = 0 
    and sl.user_id = $user_id
    and sl.account_id = $account_id
    and ig.ip & 0xFFFFFFFF = $current_ip_c & 0xffffffff
limit 1
";
    $r = $db->fetch_row_array( $sql );

    $slink_id = $r[ 'slink_id' ];
    $service_id = $r[ 'service_id' ];
    $tlink_id = $r[ 'tariff_link_id' ];
    $dp_id = $r[ 'discount_period_id' ];

    if( ! $slink_id )
        return "Can't find slink_id with external_id=$external_id and ip=$current_ip";

    $new_ip = get_free_real_ip( $current_ip );
    if( ! $new_ip )
        return "Can't find real ip for ip=$current_ip";

    $new_login = get_free_login( $external_id, '' );

    if( ! $new_login )
        return "Can't find free login for external_id=$external_id";

    $cmd = UTM_CMD . " -a link_service -user_id \"$user_id\" -account_id \"$account_id\" -service_id \"$service_id\" -discount_period_id \"$dp_id\" -tariff_link_id $tlink_id -slink_id $slink_id -iptraffic_login \"$new_login\" -iptraffic_password \"$passwd\" -dont_use_fw 0 -unabon 1 -unprepay 0 -ip_address \"$new_ip\" 2>&1";
    my_exec( $cmd );

    // Если текущий ip привязан - новый привязываем туда же
    $link_data = ldev_get_ip_link_data( $current_ip );
    if( $link_data[ 'port_id' ] )
        ldev_add_user_link( $link_data[ 'ldev_id'], $link_data[ 'port_id'], $new_ip, $new_login, $external_id );

    var_set( 'added_ip', $new_ip );

    return true;
}

function del_ip()
{
    global $db, $vars;

    $external_id = $vars['external_id']['val'];
    $ip = $vars[ 'current_ip' ][ 'val' ];
    $current_ip_c = ip2long( $current_ip );

    if( ! $current_ip_c )
        return "Bad ip $current_ip";

    $ud = get_user_data_ids( $external_id );
    if( ! is_array( $ud ) ) 
        return "Not found account with external_id=$external_id";

    $account_id = $ud['account_id'];
    
    $sql = "select sl.id id from service_links sl join iptraffic_service_links ipsl on ipsl.id = sl.id and ipsl.is_deleted = 0 join ip_groups ig on ig.ip_group_id = ipsl.ip_group_id and ig.is_deleted = 0 where sl.is_deleted = 0 and ig.ip & 0xffffffff = inet_aton( '$ip' ) and sl.account_id = $account_id";
    $r = $db->fetch_row_array( $sql );

    $slink_id = $r['id'];

    if( ! $slink_id )
        return "Can't find slink_id with external_id=$external_id and ip=$ip";

    $r = $db->fetch_row_array( "select count( ig.ip ) c from iptraffic_service_links ipsl join ip_groups ig on ig.ip_group_id = ipsl.ip_group_id and ig.is_deleted = 0 where ipsl.is_deleted = 0 and ipsl.id = $slink_id" );
    $ips_count = $r['c'];

    if( $ips_count < 2 )
        return "Can't delete last ip from slink_id $slink_id";

    $cmd = UTM_CMD . " -a delete_from_ipgroup -slink_id $slink_id -ip_address $ip 2>&1";
    my_exec( $cmd );


    // Отвязываем 
    $sql = "SELECT lnk.id, lnk.ldev_id, lnk.port_id, lnk.port_access_id, lgk.ip ldev_ip
            FROM tt.tlc_nobj_links lnk
                 join tt.tlc_nobj_logical lgk using( ldev_id )
            WHERE port_ip='$ip'";
    $rr = $db->fetch_row_array( $sql );
    $ldev_id = $rr['ldev_id']+0;
    $port_id = $rr['port_id']+0;
    $link_id = $rr['id']+0;
    $links_count = $db->fetch_row_array( "SELECT COUNT(id) c FROM tt.tlc_nobj_links WHERE ldev_id = $ldev_id AND port_id = $port_id AND port_link_id != -1" );
    if ($links_count['c'] > 1)
       $db->query( "DELETE FROM tt.tlc_nobj_links WHERE id = '$link_id'" );
    elseif( $links_count['c'] == 1 )
        $db->query( "update tt.tlc_nobj_links set port_link_id = -1, port_access_id = -1, port_ip = -1, port_username = NULL, port_vlan_id = -1, port_provider_id = -1, last_update = unix_timestamp(), status = 0 WHERE id = '$link_id'" );
    if( $links_count['c'] > 0 ) 
        $db->query( "UPDATE tt.zz_dhcpservers SET ds_update_req=1 WHERE ds_update_req=0" );

    return true;
}

function set_skk_connect()
{
	global $db, $vars;

	$scode  = ($vars['skk_code']['val']);
	$datedo = ($vars['datedo']['val']);
	$opername = ($vars['opername']['val']);	
	$answer40 = ($vars['answer40']['val']);
	$answer41 = ($vars['answer41']['val']);
	$answer42 = ($vars['answer42']['val']);
	$answer43 = ($vars['answer43']['val']);
	$answer44 = ($vars['answer44']['val']);
	$comment45 = ($vars['comment45']['val']);
	$comment46 = ($vars['comment46']['val']);
	$comment47 = ($vars['comment47']['val']);
	$comment48 = ($vars['comment48']['val']);
	$comment49 = ($vars['comment49']['val']);
	
	
	if($datedo>0) {  //Если получена  новая дата, устанавливаем ее в качестве новой даты выполнения задания
	  $datedo = $datedo;  // UTC-3h
	  $db->query("set character set utf8");	  
	  if (isset($opername)) {  // Если имеется имя оператора, записываем его в таблицу  изменений в журнале 
	     $tt = $db->fetch_row_array( "SELECT tbo.CODE FROM userside.tbl_pers as tp, userside.tbl_oper as tbo WHERE tbo.PERSCODE=tp.CODE and tp.FIO like '$opername%'");
             $opercode = $tt['CODE'];
	     $tt = $db->fetch_row_array( "SELECT datedo FROM userside.tbl_journal  WHERE code=$scode");
	     $opis = $tt['datedo'];
	     $opis = '*** $zl[1208] ***\n$zl[4676]: '.$opis.'\n $zl[4677]: '.date("Y-m-d H:i:s",$datedo);
	     $db->query( "INSERT INTO userside.tbl_journal_doing (journalcode,datedo,opercode,typer,opis) values ($scode,now(),$opercode,4,'$opis')" ); 	   	  	  
	     }
	  $db->query( "UPDATE userside.tbl_journal SET datedo=from_unixtime($datedo) WHERE code='$scode'" ); 	  	     
	  }
	else if (isset($answer40,$answer41,$answer42,$answer43,$answer44)) { //если меются все ответы на воопросы оператора, то занесем их в базу
	  $db->query("set character set utf8");
 	  $db->query( "UPDATE userside.tbl_journal SET datefinish=now(), status=2  WHERE code='$scode'" ); 	   	  

	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (40,'$scode','$answer40')" ); 	   	  
	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (41,'$scode','$answer41')" ); 	   	  	  
	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (42,'$scode','$answer42')" ); 	   	  	  
	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (43,'$scode','$answer43')" ); 	   	  	  
	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (44,'$scode','$answer44')" ); 	   	  	  
	  if (isset($opername)) { // Если имеется имя оператора, записываем его в таблицу  изменений в журнале 	  
	     $tt = $db->fetch_row_array( "SELECT tbo.CODE FROM userside.tbl_pers as tp, userside.tbl_oper as tbo WHERE tbo.PERSCODE=tp.CODE and tp.FIO like '%$opername%'");
             $opercode = $tt['CODE'];
	     $db->query( "INSERT INTO userside.tbl_journal_doing (journalcode,datedo,opercode,typer) values ($scode,now(),$opercode,11)" ); 	   	  	  	 
	     }

	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (45,'$scode','$comment45')" ); 	   	  
	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (46,'$scode','$comment46')" ); 	   	  	  
	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (47,'$scode','$comment47')" ); 	   	  	  
	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (48,'$scode','$comment48')" ); 	   	  	  
	  $db->query( "INSERT INTO userside.tbl_attr (attrcode,usercode,valuestr) values (49,'$scode','$comment49')" ); 	   	  	  
	}
	
	$r = array ($scode,$datedo,$opis,$opercode);
	var_set('report',$r);
	return true;
}



?>

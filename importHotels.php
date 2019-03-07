
<?php
$priceTours  = MySQL::fetchAll("select t.*,
    (SELECT i.img
      FROM imgs as `i`
      WHERE `i`.pid = t.`TurListKey` AND `i`.ptype = 'excurs'
      ORDER BY i.order desc, i.name ASC
      LIMIT 1) as img,
    (SELECT (SELECT GROUP_CONCAT(t2.name SEPARATOR ' — '))
      FROM tur_excurscitiesbytur as `t1`
      INNER JOIN `tur_excurscities` as `t2` ON `t1`.`idcity`=`t2`.`idcity`
      WHERE `t1`.`turlistkey` = t.`TurListKey`
      ORDER BY `t1`.`order` ASC) as exurs_path,
    (SELECT (SELECT GROUP_CONCAT( date_format(tab.date,'%d.%m') SEPARATOR ', '))
      FROM `tur_begindates` AS `tab`
      WHERE `tab`.`date` >= CURDATE() + INTERVAL 3 DAY AND `tab`.`parentId`=t.`Key`
      ORDER BY `tab`.`date` ASC
      LIMIT 5) as dates
  from (
    SELECT distinct `st`.`CityKey`, `st`.`Days`, 
      `pt`.`TurListKey`, `pt`.`MinPrice`, `pt`.`Name`, `pt`.`CountryKey`, `pt`.`Key`
    FROM `tur_pricetours` as pt
    LEFT JOIN `tur_selectexurs` AS `st` ON `st`.`PriceTourKey`=`pt`.`Key`
    WHERE `st`.`CityKey` IS NOT NULL
  ) t");

foreach ($priceTours as $key => $priceTour) {
    $query = "UPDATE `tur_describe` SET 
		`CountryKey` = '{$priceTour['CountryKey']}',
		`CityKey` = '{$priceTour['CountryKey']}',
		`min_price` = '{$priceTour['MinPrice']}',
		`title` = '{$priceTour['Name']}',
		`img` = '{$priceTour['img']}',
		`exurs_path` = '{$priceTour['exurs_path']}',
		`dates` = '{$priceTour['dates']}',
		`days` = '{$priceTour['Days']}'
		WHERE `TurListKey` = '{$priceTour['TurListKey']}'";
    $update = MySQL::query($query);
}

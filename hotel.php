<?php

namespace Models\Hotel;

use CacheModel;
use Kernel;
use Models\AbstractModel;
use Models\Helper;

/**
 * @property int $key
 * @property string $name
 * @property string $type
 * @property string $address
 */
class Hotel extends AbstractModel
{
    protected static $table = 'tur_hotels';

    private $descriptions = []; // параметры отеля (option_***)
    private $country;
    private $city;

    public function __construct($key = null, array $data = [])
    {
        parent::__construct($key, $data);
        $this->key = isset($this->data['Key']) ? $this->data['Key'] : null;
        $this->name = isset($this->data['Name']) ? $this->data['Name'] : null;
        $this->type = isset($this->data['CategoriesOfHotel']) ? $this->data['CategoriesOfHotel'] : null;
        $this->address = isset($this->data['Address']) ? $this->data['Address'] : null;
    }

    public static function loadDataByKey($id)
    {
        $query = "select h.*, hd.HD_ADDRESS as Address
                  from tur_hotels h
                  left join tur_hoteldictionary hd on hd.HD_KEY = h.`Key`
                  where h.`Key` = :id";
        return self::db()->fetchRow($query, ['id' => $id]);
    }

    public function getFullName()
    {
        return $this->name . ' ' . $this->type;
    }

    /**
     * Ссылка на отель
     */
    public function getUrl()
    {
        return isset($this->data['s_modrewrite']) ? '/hotel/' . trim($this->data['s_modrewrite'], '/') : null;
    }

    /**
     * Картинки отеля
     * @return Image[]
     */
    public function getImages()
    {
        return Image::getImages('hotel', $this->key);
    }

    /**
     * Код для вставки видео
     */
    public function getVideo()
    {
        return isset($this->data['s_video']) ? $this->data['s_video'] : null;
    }

    /**
     * Параметры отеля
     * @return array
     * @throws \Zend_Db_Statement_Exception
     */
    public function getDescriptions()
    {
        if (empty($this->descriptions)) {
            $query = "select op.name, op.desc from tur_hoteloptions op where op.hotel = :hotel_key";

            foreach (self::db()->fetchAll($query, ['hotel_key' => $this->key]) as $_) {
                if (!empty($_['desc'])) {
                    if (!empty($this->descriptions[$_['name']])) {
                        $this->descriptions[$_['name']] .= PHP_EOL . $_['desc'];
                    } else {
                        $this->descriptions[$_['name']] = $_['desc'];
                    }
                }
            }
        }
        return $this->descriptions;
    }

    /** Получение одного параметра
     * @param $key
     * @param bool $html
     * @return string
     * @throws \Zend_Db_Statement_Exception
     */
    public function getDescription($key, $html = true)
    {
        $descriptions = $this->getDescriptions();
        $desc = isset($descriptions[$key]) ? $descriptions[$key] : null;
        return $html ? $desc : strip_tags($desc);
    }

    /**
     * Основные услуги отеля
     * @return array
     */
    public function getBasicServices()
    {
        $services = [];
        $description = $this->getDescriptions();
        $items = [
            'Бассейн', 'СПА-центр', 'Боулинг', 'Детская комната', 'Проживание в коттеджах', 'Анимация', 'Рыбалка', 'Конференц-зал',
            'Интернет', 'Кондиционер в номере', 'Проживание с животными', 'Собственный бювет', 'Возможность подселения',
            'Питание «шведский стол»', 'Кухня в номере', 'Лошади', 'Парковка'
        ];
        foreach ($items as $item) {
            if (isset($description[$item])) {
                $services[] = $item;
            }
        }
        return $services;
    }

    /**
     * Виды лечения по отелю
     * @return array
     */
    public function getTreatments()
    {
        $treatments = [];
        $description = $this->getDescriptions();
        if (isset($description['Опорно-двигательная система'])) {
            $treatments[] = 'Опорно-двигательная система';
        }
        if (isset($description['Нервная система'])) {
            $treatments[] = 'Нервная система';
        }
        if (isset($description['Пищеварительная система (ЖКТ)'])) {
            $treatments[] = 'Пищеварительная система (ЖКТ)';
        }
        if (isset($description['Эндокринная система'])) {
            $treatments[] = 'Эндокринная система';
        }
        if (isset($description['Почки и мочевыводящие пути'])) {
            $treatments[] = 'Почки и мочевыводящие пути';
        }
        if (isset($description['Органы дыхания'])) {
            $treatments[] = 'Органы дыхания';
        }
        if (isset($description['Органы зрения'])) {
            $treatments[] = 'Органы зрения';
        }
        if (isset($description['Гинекология'])) {
            $treatments[] = 'Гинекология';
        }
        if (isset($description[''])) {
            $treatments[] = 'Кожные заболевания';
        }
        return $treatments;
    }

    /**
     * Акции отеля
     * @return array
     */
    public function getPromotions()
    {
        $promotions = [];
        $description = $this->getDescriptions();
        if (isset($description['Акция отеля'])) {
            if (preg_match_all("/<ul>(.*)<\/ul>/sU", $description['Акция отеля'], $blocks)) {
                foreach ($blocks[1] as $block) {
                    if (preg_match_all("/<li>(.*)<\/li>/sU", $block, $items)) {
                        $title = isset($items[1][0]) ? strip_tags($items[1][0]) : null;
                        $promotions[] = [
                            'title' => trim(str_replace('Акция', '', $title)),
                            'period' => isset($items[1][1]) ? strip_tags($items[1][1]) : null,
                            'short_desc' => isset($items[1][2]) ? strip_tags($items[1][2]) : null,
                            'full_desc' => isset($items[1][3]) ? strip_tags($items[1][3]) : null,
                        ];
                    }
                }
            }
        }
        return $promotions;
    }

    /**
     * "Внимание" из описания
     * @return string|null
     * @throws \Zend_Db_Statement_Exception
     */
    public function getAttention()
    {
        return $this->getDescription('Внимание');
    }

    /**
     * Страна
     * @return Country
     */
    public function getCountry()
    {
        if (!$this->country) {
            $countryKey = !empty($this->data['CountryKey']) ? $this->data['CountryKey'] : null;
            $this->country = new Country($countryKey);
        }
        return $this->country;
    }

    /**
     * Город
     * @return City
     */
    public function getCity()
    {
        if (!$this->city) {
            $this->city = new City($this->data['CityKey']);
        }
        return $this->city;
    }

    /**
     * Поиск доступных данные для заказа для конкретного отеля
     * @return array
     * @throws \Zend_Db_Statement_Exception
     * @throws \Zend_Cache_Exception
     */
    public function getRanges()
    {
        /** @var CacheModel $cacheModel */
        $cacheModel = Kernel::getModel('Cache', 'Turs');
        $cache_temp = $cacheModel->get('hotelRange', 3600);

        $cacheData = $cache_temp->load($this->key);
        if ($cacheData) {
            return $cacheData;
        }

        $query = "select
            min(ps.Days) as minDays,
            max(ps.Days) as maxDays,
            min(prc.Gross) as minPrice,
            max(prc.Gross) as maxPrice,
            UNIX_TIMESTAMP(min(prc.DateBegin)) as minDate,
            UNIX_TIMESTAMP(max(prc.DateBegin)) as maxDate,
            min(acc.NRealPlacesAdult) as minAdult,
            max(acc.NRealPlacesAdult) as maxAdult
          from `tur_prices` AS `prc`
          inner join tur_pricetours AS pt ON pt.Key = prc.PriceTourKey
          left join tur_priceservicelists AS psl ON prc.PriceListKey = psl.PriceListKey
          left join tur_priceservices AS ps ON psl.PriceServiceKey = ps.Key
          left join tur_rooms room on room.Key = ps.SubCode1
          left join tur_accommodations acc on acc.Key = room.AccmdMenTypeKey
          where ps.Code = :hotel_key and ps.ServiceKey = 3";
        $ranges = self::db()->fetchRow($query, ['hotel_key' => $this->key]);

        $query = "select distinct  DATE_FORMAT(prc.DateBegin,'%d.%m.%Y') as dt
          from `tur_prices` AS `prc`
          inner join tur_pricetours AS pt ON pt.Key = prc.PriceTourKey
          left join tur_priceservicelists AS psl ON prc.PriceListKey = psl.PriceListKey
          left join tur_priceservices AS ps ON psl.PriceServiceKey = ps.Key
          left join tur_rooms room on room.Key = ps.SubCode1
          left join tur_accommodations acc on acc.Key = room.AccmdMenTypeKey
          where prc.DateBegin >= curdate() and ps.Code = :hotel_key and ps.ServiceKey = 3
          order by prc.DateBegin";
        $ranges['dates'] = array_column(self::db()->fetchAll($query, ['hotel_key' => $this->key]), 'dt');

        $cache_temp->save($ranges);

        return $ranges;
    }

    public function paramsForSearch()
    {
        $query = "SELECT hp.* FROM `hotel_prices` AS hp WHERE `hotel_id` = :hid AND `tour_date` >= ( CURDATE() + INTERVAL 3 DAY ) ORDER BY  `tour_date` ASC";
        $result = self::db()->fetchRow($query, ['hid' => $this->key]);

        return $result;
    }


    /**
     * Координаты отеля
     * @return array
     * @throws \Zend_Db_Statement_Exception
     */
    public function getCoords()
    {
        $query = "SELECT x as lat, y as lng FROM `maps` WHERE `pid` = :pid AND `ptype` = 'hotel'";
        $result = self::db()->fetchRow($query, ['pid' => $this->key]);

        if (empty($result)) {
            return $this->getCity()->getCoords();
        }

        return $result;
    }

    /**
     * Минимальная цена
     *
     * @return |null
     * @throws \Zend_Db_Statement_Exception
     */
    public function getMinPrice()
    {
        $query = "SELECT tpt.`MinPrice` AS `price`
                  FROM `tur_priceservices` AS tps 
                      LEFT JOIN `tur_pricetours` as tpt ON (tpt.`Key` = tps.PriceTourKey)
                      WHERE tps.Code = :key
                      LIMIT 1
                  ";

        $result = self::db()->fetchRow($query, ['key' => $this->key]);
        return !empty($result) ? $result['price'] : null;
    }

    /**
     * Поххожие отели
     *
     * @param int $limit
     * @return array|bool|false|mixed
     * @throws \Zend_Cache_Exception
     * @throws \Zend_Db_Statement_Exception
     */
    public function findSimilarHotels($limit = 5)
    {
        /** @var CacheModel $cacheModel */
        $cacheModel = Kernel::getModel('Cache', 'Turs');
        $cache_temp = $cacheModel->get('hotelFindSimilar', 3600 * 24);
        $cacheData = $cache_temp->load($this->key);
        if ($cacheData) {
            return $cacheData;
        }

        $hotels = [];
        $coords = $this->getCoords();

        $query = "SELECT DISTINCT h.Key, h.*, (dist( :lat, :lng, m.x, m.y)) as distance,
                  (SELECT tpt.`MinPrice` 
                   FROM `tur_priceservices` as tps
                        LEFT JOIN `tur_pricetours` as tpt ON tpt.`Key` = tps.PriceTourKey
                        WHERE tps.Code = h.Key LIMIT 1) as price
                  FROM `tur_hotels` as h  
                    LEFT JOIN `maps` as m ON (m.pid = h.Key)
                  WHERE 
                      h.CityKey = :cityKey AND
                      h.CountryKey = :countryKey AND
                      h.Key != :key
                  HAVING price != '' AND distance IS NOT NULL
                  ORDER BY distance ASC
                  LIMIT {$limit}";

        $result = self::db()->fetchAll($query, ['lat' => $coords['lat'], 'lng' => $coords['lng'], 'cityKey' => $this->data['CityKey'],
            'countryKey' => $this->data['CountryKey'], 'key' => $this->key]);
        foreach ($result as $hotel) {
            $hotels[] = new $this(null, $hotel);
        }

        $cache_temp->save($hotels);
        return $hotels;
    }

    /**
     * Расчет расстояния
     *
     * @param $hotel
     * @param bool $convert
     * @return float|int|string
     * @throws \Zend_Db_Statement_Exception
     */
    public function getDistance($hotel, $convert = false)
    {
        $coordThis = $this->getCoords();
        $coordHotel = $hotel->getCoords();

        $distance = Helper::getDistance($coordThis['lat'], $coordThis['lng'], $coordHotel['lat'], $coordHotel['lng']);

        if ($convert) {

            if ($distance < 20) {
                $text = "непосредственной близости";
            } else if ($distance < 1000) {
                $length = number_format($distance, 0, '.', ' ');
                $text = "{$length} м";
            } else {
                $length = number_format($distance / 1000, 0, '.', ' ');
                $text = "{$length} км";
            }
            return $text;
        }

        return $distance;
    }

}


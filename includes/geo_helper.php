<?php
/**
 * Location helpers for checkout Country / Province / Town cascading selects.
 */

function fh_geo_ensure_seeded($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $check = @$conn->query("SHOW TABLES LIKE 'geo_countries'");
    if (!$check || $check->num_rows === 0) {
        return;
    }
    $countSql = $conn->query("SELECT COUNT(*) AS c FROM geo_countries");
    $count = ($countSql && ($r = $countSql->fetch_assoc())) ? (int)$r['c'] : 0;
    if ($count > 0) {
        fh_geo_sync_zipcodes($conn);
        fh_geo_sync_barangays($conn);
        return;
    }

    $countries = array(
        array('Philippines', 'PH'),
        array('United States', 'US'),
        array('Canada', 'CA'),
        array('United Kingdom', 'GB'),
        array('Australia', 'AU'),
        array('Japan', 'JP'),
        array('South Korea', 'KR'),
        array('China', 'CN'),
        array('Singapore', 'SG'),
        array('Malaysia', 'MY'),
        array('Indonesia', 'ID'),
        array('Thailand', 'TH'),
        array('Vietnam', 'VN'),
        array('Taiwan', 'TW'),
        array('Hong Kong', 'HK'),
        array('United Arab Emirates', 'AE'),
        array('Saudi Arabia', 'SA'),
        array('Germany', 'DE'),
        array('France', 'FR'),
        array('Italy', 'IT'),
        array('Spain', 'ES'),
        array('Netherlands', 'NL'),
        array('New Zealand', 'NZ'),
        array('India', 'IN'),
        array('Other', 'XX')
    );

    foreach ($countries as $c) {
        $name = $conn->real_escape_string($c[0]);
        $code = $conn->real_escape_string($c[1]);
        $conn->query("INSERT IGNORE INTO geo_countries (country_name, country_code) VALUES ('$name', '$code')");
    }

    $phSql = $conn->query("SELECT country_id FROM geo_countries WHERE country_name = 'Philippines' LIMIT 1");
    if (!$phSql || !($ph = $phSql->fetch_assoc())) {
        return;
    }
    $phId = (int)$ph['country_id'];

    $provincesTowns = fh_geo_ph_provinces_towns();
    foreach ($provincesTowns as $province => $towns) {
        $pName = $conn->real_escape_string($province);
        $conn->query("INSERT IGNORE INTO geo_provinces (country_id, province_name) VALUES ($phId, '$pName')");
        $pSql = $conn->query("SELECT province_id FROM geo_provinces WHERE country_id = $phId AND province_name = '$pName' LIMIT 1");
        if (!$pSql || !($prow = $pSql->fetch_assoc())) {
            continue;
        }
        $provinceId = (int)$prow['province_id'];
        foreach ($towns as $town) {
            $tName = $conn->real_escape_string($town);
            $zip = fh_geo_lookup_zip($province, $town);
            $safeZip = $zip !== '' ? "'" . $conn->real_escape_string($zip) . "'" : 'NULL';
            $conn->query("INSERT IGNORE INTO geo_towns (province_id, town_name, zipcode) VALUES ($provinceId, '$tName', $safeZip)");
            if ($zip !== '') {
                $conn->query("UPDATE geo_towns SET zipcode = '" . $conn->real_escape_string($zip) . "' WHERE province_id = $provinceId AND town_name = '$tName' AND (zipcode IS NULL OR zipcode = '')");
            }
        }
    }
    fh_geo_sync_zipcodes($conn);
    fh_geo_sync_barangays($conn);
}

function fh_geo_lookup_zip($province, $town) {
    $map = fh_geo_zipcode_map();
    $key = $province . '|' . $town;
    return isset($map[$key]) ? $map[$key] : '';
}

function fh_geo_sync_zipcodes($conn) {
    static $synced = false;
    if ($synced) {
        return;
    }
    $synced = true;
    $map = fh_geo_zipcode_map();
    foreach ($map as $key => $zip) {
        $parts = explode('|', $key, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $province = $conn->real_escape_string($parts[0]);
        $town = $conn->real_escape_string($parts[1]);
        $safeZip = $conn->real_escape_string($zip);
        $conn->query(
            "UPDATE geo_towns gt
             INNER JOIN geo_provinces gp ON gp.province_id = gt.province_id
             SET gt.zipcode = '$safeZip'
             WHERE gp.province_name = '$province' AND gt.town_name = '$town'"
        );
    }
}

function fh_geo_zipcode_map() {
    // Key format: Province|Town / City
    return array(
        // Bohol (complete)
        'Bohol|Tagbilaran City' => '6300',
        'Bohol|Baclayon' => '6301',
        'Bohol|Alburquerque' => '6302',
        'Bohol|Loay' => '6303',
        'Bohol|Lila' => '6304',
        'Bohol|Dimiao' => '6305',
        'Bohol|Valencia' => '6306',
        'Bohol|Garcia Hernandez' => '6307',
        'Bohol|Jagna' => '6308',
        'Bohol|Duero' => '6309',
        'Bohol|Guindulman' => '6310',
        'Bohol|Anda' => '6311',
        'Bohol|Candijay' => '6312',
        'Bohol|Mabini' => '6313',
        'Bohol|Alicia' => '6314',
        'Bohol|Ubay' => '6315',
        'Bohol|Loboc' => '6316',
        'Bohol|Bilar' => '6317',
        'Bohol|Batuan' => '6318',
        'Bohol|Carmen' => '6319',
        'Bohol|Sierra Bullones' => '6320',
        'Bohol|Pilar' => '6321',
        'Bohol|Dagohoy' => '6322',
        'Bohol|San Miguel' => '6323',
        'Bohol|Trinidad' => '6324',
        'Bohol|Talibon' => '6325',
        'Bohol|Bien Unido' => '6326',
        'Bohol|Loon' => '6327',
        'Bohol|Calape' => '6328',
        'Bohol|Tubigon' => '6329',
        'Bohol|Clarin' => '6330',
        'Bohol|Sagbayan' => '6331',
        'Bohol|Inabanga' => '6332',
        'Bohol|Buenavista' => '6333',
        'Bohol|Getafe' => '6334',
        'Bohol|Antequera' => '6335',
        'Bohol|Maribojoc' => '6336',
        'Bohol|Corella' => '6337',
        'Bohol|Sikatuna' => '6338',
        'Bohol|Dauis' => '6339',
        'Bohol|Panglao' => '6340',
        'Bohol|Cortes' => '6341',
        'Bohol|Balilihan' => '6342',
        'Bohol|Catigbian' => '6343',
        'Bohol|Danao' => '6344',
        'Bohol|San Isidro' => '6345',
        'Bohol|President Carlos P. Garcia' => '6346',
        'Bohol|Sevilla' => '6347',
        // Major cities (sample nationwide)
        'Metro Manila|City of Manila' => '1000',
        'Metro Manila|Quezon City' => '1100',
        'Metro Manila|Makati City' => '1200',
        'Metro Manila|Pasig City' => '1600',
        'Metro Manila|Taguig City' => '1630',
        'Metro Manila|Parañaque City' => '1700',
        'Metro Manila|Las Piñas City' => '1740',
        'Metro Manila|Muntinlupa City' => '1770',
        'Metro Manila|Pasay City' => '1300',
        'Metro Manila|Mandaluyong City' => '1550',
        'Metro Manila|San Juan City' => '1500',
        'Metro Manila|Marikina City' => '1800',
        'Metro Manila|Caloocan City' => '1400',
        'Metro Manila|Malabon City' => '1470',
        'Metro Manila|Navotas City' => '1485',
        'Metro Manila|Valenzuela City' => '1440',
        'Cebu|Cebu City' => '6000',
        'Cebu|Mandaue City' => '6014',
        'Cebu|Lapu-Lapu City' => '6015',
        'Cebu|Talisay City' => '6045',
        'Negros Occidental|Bacolod City' => '6100',
        'Iloilo|Iloilo City' => '5000',
        'Davao del Sur|Davao City' => '8000',
        'Misamis Oriental|Cagayan de Oro City' => '9000',
        'South Cotabato|General Santos City' => '9500',
        'Zamboanga del Sur|Zamboanga City' => '7000',
        'Pampanga|Angeles City' => '2009',
        'Pampanga|San Fernando City' => '2000',
        'Laguna|Calamba City' => '4027',
        'Laguna|Santa Rosa City' => '4026',
        'Cavite|Dasmariñas City' => '4114',
        'Cavite|Bacoor City' => '4102',
        'Batangas|Batangas City' => '4200',
        'Batangas|Lipa City' => '4217',
        'Bulacan|Malolos City' => '3000',
        'Rizal|Antipolo City' => '1870',
        'Pangasinan|Dagupan City' => '2400',
        'La Union|San Fernando City' => '2500',
        'Ilocos Norte|Laoag City' => '2900',
        'Ilocos Sur|Vigan City' => '2700',
        'Albay|Legazpi City' => '4500',
        'Camarines Sur|Naga City' => '4400',
        'Leyte|Tacloban City' => '6500',
        'Samar|Catbalogan City' => '6700',
        'Palawan|Puerto Princesa City' => '5300',
        'Benguet|Baguio City' => '2600'
    );
}

function fh_geo_ph_provinces_towns() {
    return array(
        'Abra' => array('Bangued', 'Boliney', 'Bucay', 'Bucloc', 'Daguioman', 'Danglas', 'Dolores', 'La Paz', 'Lacub', 'Lagangilang', 'Lagayan', 'Langiden', 'Licuan-Baay', 'Luba', 'Malibcong', 'Manabo', 'Peñarrubia', 'Pidigan', 'Pilar', 'Sallapadan', 'San Isidro', 'San Juan', 'San Quintin', 'Tayum', 'Tineg', 'Tubo', 'Villaviciosa'),
        'Agusan del Norte' => array('Butuan City', 'Cabadbaran City', 'Buenavista', 'Carmen', 'Jabonga', 'Kitcharao', 'Las Nieves', 'Magallanes', 'Nasipit', 'Remedios T. Romualdez', 'Santiago', 'Tubay'),
        'Agusan del Sur' => array('Bayugan City', 'Bunawan', 'Esperanza', 'La Paz', 'Loreto', 'Prosperidad', 'Rosario', 'San Francisco', 'San Luis', 'Santa Josefa', 'Sibagat', 'Talacogon', 'Trento', 'Veruela'),
        'Aklan' => array('Kalibo', 'Altavas', 'Balete', 'Banga', 'Batan', 'Buruanga', 'Ibajay', 'Lezo', 'Libacao', 'Madalag', 'Makato', 'Malay', 'Malinao', 'Nabas', 'New Washington', 'Numancia', 'Tangalan'),
        'Albay' => array('Legazpi City', 'Ligao City', 'Tabaco City', 'Bacacay', 'Camalig', 'Daraga', 'Guinobatan', 'Jovellar', 'Libon', 'Malilipot', 'Malinao', 'Manito', 'Oas', 'Pio Duran', 'Polangui', 'Rapu-Rapu', 'Santo Domingo', 'Tiwi'),
        'Antique' => array('San Jose de Buenavista', 'Anini-y', 'Barbaza', 'Belison', 'Bugasong', 'Caluya', 'Culasi', 'Hamtic', 'Laua-an', 'Libertad', 'Pandan', 'Patnongon', 'Sebaste', 'Sibalom', 'Tibiao', 'Tobias Fornier', 'Valderrama'),
        'Apayao' => array('Kabugao', 'Calanasan', 'Conner', 'Flora', 'Luna', 'Pudtol', 'Santa Marcela'),
        'Aurora' => array('Baler', 'Casiguran', 'Dilasag', 'Dinalungan', 'Dingalan', 'Dipaculao', 'Maria Aurora', 'San Luis'),
        'Basilan' => array('Isabela City', 'Lamitan City', 'Akbar', 'Al-Barka', 'Hadji Mohammad Ajul', 'Hadji Muhtamad', 'Lantawan', 'Maluso', 'Sumisip', 'Tabuan-Lasa', 'Tipo-Tipo', 'Tuburan', 'Ungkaya Pukan'),
        'Bataan' => array('Balanga City', 'Abucay', 'Bagac', 'Dinalupihan', 'Hermosa', 'Limay', 'Mariveles', 'Morong', 'Orani', 'Orion', 'Pilar', 'Samal'),
        'Batanes' => array('Basco', 'Itbayat', 'Ivana', 'Mahatao', 'Sabtang', 'Uyugan'),
        'Batangas' => array('Batangas City', 'Lipa City', 'Tanauan City', 'Santo Tomas City', 'Agoncillo', 'Alitagtag', 'Balayan', 'Balete', 'Bauan', 'Calaca', 'Calatagan', 'Cuenca', 'Ibaan', 'Laurel', 'Lemery', 'Lian', 'Lobo', 'Mabini', 'Malvar', 'Mataasnakahoy', 'Nasugbu', 'Padre Garcia', 'Rosario', 'San Jose', 'San Juan', 'San Luis', 'San Nicolas', 'San Pascual', 'Santa Teresita', 'Taal', 'Talisay', 'Taysan', 'Tingloy', 'Tuy'),
        'Benguet' => array('Baguio City', 'La Trinidad', 'Atok', 'Bakun', 'Bokod', 'Buguias', 'Itogon', 'Kabayan', 'Kapangan', 'Kibungan', 'Mankayan', 'Sablan', 'Tuba', 'Tublay'),
        'Biliran' => array('Naval', 'Almeria', 'Biliran', 'Cabucgayan', 'Caibiran', 'Culaba', 'Kawayan', 'Maripipi'),
        'Bohol' => array(
            'Tagbilaran City', 'Alburquerque', 'Alicia', 'Anda', 'Antequera', 'Baclayon', 'Balilihan',
            'Batuan', 'Bien Unido', 'Bilar', 'Buenavista', 'Calape', 'Candijay', 'Carmen', 'Catigbian',
            'Clarin', 'Corella', 'Cortes', 'Dagohoy', 'Danao', 'Dauis', 'Dimiao', 'Duero',
            'Garcia Hernandez', 'Getafe', 'Guindulman', 'Inabanga', 'Jagna', 'Lila', 'Loay', 'Loboc',
            'Loon', 'Mabini', 'Maribojoc', 'Panglao', 'Pilar', 'President Carlos P. Garcia', 'Sagbayan',
            'San Isidro', 'San Miguel', 'Sevilla', 'Sierra Bullones', 'Sikatuna', 'Talibon', 'Trinidad',
            'Tubigon', 'Ubay', 'Valencia'
        ),
        'Bukidnon' => array('Malaybalay City', 'Valencia City', 'Baungon', 'Cabanglasan', 'Damulog', 'Dangcagan', 'Don Carlos', 'Impasugong', 'Kadingilan', 'Kalilangan', 'Kibawe', 'Kitaotao', 'Lantapan', 'Libona', 'Malitbog', 'Manolo Fortich', 'Maramag', 'Pangantucan', 'Quezon', 'San Fernando', 'Sumilao', 'Talakag'),
        'Bulacan' => array('Malolos City', 'Meycauayan City', 'San Jose del Monte City', 'Angat', 'Balagtas', 'Baliuag', 'Bocaue', 'Bulakan', 'Bustos', 'Calumpit', 'Doña Remedios Trinidad', 'Guiguinto', 'Hagonoy', 'Marilao', 'Norzagaray', 'Obando', 'Pandi', 'Paombong', 'Plaridel', 'Pulilan', 'San Ildefonso', 'San Miguel', 'San Rafael', 'Santa Maria'),
        'Cagayan' => array('Tuguegarao City', 'Abulug', 'Alcala', 'Allacapan', 'Amulung', 'Aparri', 'Bagao', 'Ballesteros', 'Buguey', 'Calayan', 'Camalaniugan', 'Claveria', 'Enrile', 'Gattaran', 'Gonzaga', 'Iguig', 'Lal-lo', 'Lasam', 'Pamplona', 'Peñablanca', 'Piat', 'Rizal', 'Sanchez-Mira', 'Santa Ana', 'Santa Praxedes', 'Santa Teresita', 'Santo Niño', 'Solana', 'Tuao'),
        'Camarines Norte' => array('Daet', 'Basud', 'Capalonga', 'Jose Panganiban', 'Labo', 'Mercedes', 'Paracale', 'San Lorenzo Ruiz', 'San Vicente', 'Santa Elena', 'Talisay', 'Vinzons'),
        'Camarines Sur' => array('Naga City', 'Iriga City', 'Baao', 'Balatan', 'Bato', 'Bombon', 'Buhi', 'Bula', 'Cabusao', 'Calabanga', 'Camaligan', 'Canaman', 'Caramoan', 'Del Gallego', 'Gainza', 'Garchitorena', 'Goa', 'Lagonoy', 'Libmanan', 'Lupi', 'Magarao', 'Milaor', 'Minalabac', 'Nabua', 'Ocampo', 'Pamplona', 'Pasacao', 'Pili', 'Presentacion', 'Ragay', 'Sagñay', 'San Fernando', 'San Jose', 'Sipocot', 'Siruma', 'Tigaon', 'Tinambac'),
        'Camiguin' => array('Mambajao', 'Catarman', 'Guinsiliban', 'Mahinog', 'Sagay'),
        'Capiz' => array('Roxas City', 'Cuartero', 'Dao', 'Dumalag', 'Dumarao', 'Ivisan', 'Jamindan', 'Ma-ayon', 'Mambusao', 'Panay', 'Panitan', 'Pilar', 'Pontevedra', 'President Roxas', 'Sapi-an', 'Sigma', 'Tapaz'),
        'Catanduanes' => array('Virac', 'Bagamanoc', 'Baras', 'Bato', 'Caramoran', 'Gigmoto', 'Pandan', 'Panganiban', 'San Andres', 'San Miguel', 'Viga'),
        'Cavite' => array('Bacoor City', 'Cavite City', 'Dasmariñas City', 'General Trias City', 'Imus City', 'Tagaytay City', 'Trece Martires City', 'Alfonso', 'Amadeo', 'Carmona', 'General Emilio Aguinaldo', 'General Mariano Alvarez', 'Indang', 'Kawit', 'Magallanes', 'Maragondon', 'Mendez', 'Naic', 'Noveleta', 'Rosario', 'Silang', 'Tanza', 'Ternate'),
        'Cebu' => array('Cebu City', 'Carcar City', 'Danao City', 'Lapu-Lapu City', 'Mandaue City', 'Naga City', 'Talisay City', 'Toledo City', 'Alcantara', 'Alcoy', 'Alegria', 'Aloguinsan', 'Argao', 'Asturias', 'Badian', 'Balamban', 'Bantayan', 'Barili', 'Bogo City', 'Boljoon', 'Borbon', 'Carmen', 'Catmon', 'Compostela', 'Consolacion', 'Cordova', 'Daanbantayan', 'Dalaguete', 'Dumanjug', 'Ginatilan', 'Liloan', 'Madridejos', 'Malabuyoc', 'Medellin', 'Minglanilla', 'Moalboal', 'Oslob', 'Pilar', 'Pinamungajan', 'Poro', 'Ronda', 'Samboan', 'San Fernando', 'San Francisco', 'San Remigio', 'Santa Fe', 'Santander', 'Sibonga', 'Sogod', 'Tabogon', 'Tabuelan', 'Tuburan', 'Tudela'),
        'Cotabato' => array('Kidapawan City', 'Alamada', 'Aleosan', 'Antipas', 'Arakan', 'Banisilan', 'Carmen', 'Kabacan', 'Libungan', 'M\'lang', 'Magpet', 'Makilala', 'Matalam', 'Midsayap', 'Pigcawayan', 'Pikit', 'President Roxas', 'Tulunan'),
        'Davao de Oro' => array('Nabunturan', 'Compostela', 'Laak', 'Mabini', 'Maco', 'Maragusan', 'Mawab', 'Monkayo', 'Montevista', 'New Bataan', 'Pantukan'),
        'Davao del Norte' => array('Tagum City', 'Panabo City', 'Island Garden City of Samal', 'Asuncion', 'Braulio E. Dujali', 'Carmen', 'Kapalong', 'New Corella', 'San Isidro', 'Santo Tomas', 'Talaingod'),
        'Davao del Sur' => array('Digos City', 'Bansalan', 'Hagonoy', 'Kiblawan', 'Magsaysay', 'Malalag', 'Matanao', 'Padada', 'Santa Cruz', 'Sulop'),
        'Davao Occidental' => array('Malita', 'Don Marcelino', 'Jose Abad Santos', 'Santa Maria', 'Sarangani'),
        'Davao Oriental' => array('Mati City', 'Baganga', 'Banaybanay', 'Boston', 'Caraga', 'Cateel', 'Governor Generoso', 'Lupon', 'Manay', 'San Isidro', 'Tarragona'),
        'Dinagat Islands' => array('San Jose', 'Basilisa', 'Cagdianao', 'Dinagat', 'Libjo', 'Loreto', 'Tubajon'),
        'Eastern Samar' => array('Borongan City', 'Arteche', 'Balangiga', 'Balangkayan', 'Can-avid', 'Dolores', 'General MacArthur', 'Giporlos', 'Guiuan', 'Hernani', 'Jipapad', 'Lawaan', 'Llorente', 'Maslog', 'Maydolong', 'Mercedes', 'Oras', 'Quinapondan', 'Salcedo', 'San Julian', 'San Policarpo', 'Sulat', 'Taft'),
        'Guimaras' => array('Jordan', 'Buenavista', 'Nueva Valencia', 'San Lorenzo', 'Sibunag'),
        'Ifugao' => array('Lagawe', 'Aguinaldo', 'Alfonso Lista', 'Asipulo', 'Banaue', 'Hingyon', 'Hungduan', 'Kiangan', 'Lamut', 'Mayoyao', 'Tinoc'),
        'Ilocos Norte' => array('Laoag City', 'Batac City', 'Adams', 'Bacarra', 'Badoc', 'Bangui', 'Banna', 'Burgos', 'Carasi', 'Currimao', 'Dingras', 'Dumalneg', 'Marcos', 'Nueva Era', 'Pagudpud', 'Paoay', 'Pasuquin', 'Piddig', 'Pinili', 'San Nicolas', 'Sarrat', 'Solsona', 'Vintar'),
        'Ilocos Sur' => array('Vigan City', 'Candon City', 'Alilem', 'Banayoyo', 'Bantay', 'Burgos', 'Cabugao', 'Caoayan', 'Cervantes', 'Galimuyod', 'Gregorio del Pilar', 'Lidlidda', 'Magsingal', 'Nagbukel', 'Narvacan', 'Quirino', 'Salcedo', 'San Emilio', 'San Esteban', 'San Ildefonso', 'San Juan', 'San Vicente', 'Santa', 'Santa Catalina', 'Santa Cruz', 'Santa Lucia', 'Santa Maria', 'Santiago', 'Santo Domingo', 'Sigay', 'Sinait', 'Sugpon', 'Suyo', 'Tagudin'),
        'Iloilo' => array('Iloilo City', 'Passi City', 'Ajuy', 'Alimodian', 'Anilao', 'Badiangan', 'Balasan', 'Banate', 'Barotac Nuevo', 'Barotac Viejo', 'Batad', 'Bingawan', 'Cabatuan', 'Calinog', 'Carles', 'Concepcion', 'Dingle', 'Dueñas', 'Dumangas', 'Estancia', 'Guimbal', 'Igbaras', 'Janiuay', 'Lambunao', 'Leganes', 'Lemery', 'Leon', 'Maasin', 'Miagao', 'Mina', 'New Lucena', 'Oton', 'Pavia', 'Pototan', 'San Dionisio', 'San Enrique', 'San Joaquin', 'San Miguel', 'San Rafael', 'Santa Barbara', 'Sara', 'Tigbauan', 'Tubungan', 'Zarraga'),
        'Isabela' => array('Ilagan City', 'Cauayan City', 'Santiago City', 'Alicia', 'Angadanan', 'Aurora', 'Benito Soliven', 'Burgos', 'Cabagan', 'Cabatuan', 'Cordon', 'Delfin Albano', 'Dinapigue', 'Divilacan', 'Echague', 'Gamu', 'Jones', 'Luna', 'Maconacon', 'Mallig', 'Naguilian', 'Palanan', 'Quezon', 'Quirino', 'Ramon', 'Reina Mercedes', 'Roxas', 'San Agustin', 'San Guillermo', 'San Isidro', 'San Manuel', 'San Mariano', 'San Mateo', 'San Pablo', 'Santa Maria', 'Santo Tomas', 'Tumauini'),
        'Kalinga' => array('Tabuk City', 'Balbalan', 'Lubuagan', 'Pasil', 'Pinukpuk', 'Rizal', 'Tanudan', 'Tinglayan'),
        'La Union' => array('San Fernando City', 'Agoo', 'Aringay', 'Bacnotan', 'Bagulin', 'Balaoan', 'Bangar', 'Bauang', 'Burgos', 'Caba', 'Luna', 'Naguilian', 'Pugo', 'Rosario', 'San Gabriel', 'San Juan', 'Santo Tomas', 'Santol', 'Sudipen', 'Tubao'),
        'Laguna' => array('Calamba City', 'San Pablo City', 'Santa Rosa City', 'Biñan City', 'Cabuyao City', 'San Pedro City', 'Alaminos', 'Bay', 'Calauan', 'Cavinti', 'Famy', 'Kalayaan', 'Liliw', 'Los Baños', 'Luisiana', 'Lumban', 'Mabitac', 'Magdalena', 'Majayjay', 'Nagcarlan', 'Paete', 'Pagsanjan', 'Pakil', 'Pangil', 'Pila', 'Rizal', 'Santa Cruz', 'Santa Maria', 'Siniloan', 'Victoria'),
        'Lanao del Norte' => array('Iligan City', 'Tubod', 'Bacolod', 'Baloi', 'Baroy', 'Kapatagan', 'Kauswagan', 'Kolambugan', 'Lala', 'Linamon', 'Magsaysay', 'Maigo', 'Matungao', 'Munai', 'Nunungan', 'Pantao Ragat', 'Pantar', 'Poona Piagapo', 'Salvador', 'Sapad', 'Sultan Naga Dimaporo', 'Tagoloan', 'Tangcal'),
        'Lanao del Sur' => array('Marawi City', 'Bacolod-Kalawi', 'Balabagan', 'Balindong', 'Bayang', 'Binidayan', 'Buadiposo-Buntong', 'Bubong', 'Butig', 'Calanogas', 'Ditsaan-Ramain', 'Ganassi', 'Kapai', 'Kapatagan', 'Lumba-Bayabao', 'Lumbaca-Unayan', 'Lumbatan', 'Lumbayanague', 'Madalum', 'Madamba', 'Maguing', 'Malabang', 'Marantao', 'Marogong', 'Masiu', 'Mulondo', 'Pagayawan', 'Piagapo', 'Picong', 'Poona Bayabao', 'Pualas', 'Saguiaran', 'Sultan Dumalondong', 'Tagoloan II', 'Tamparan', 'Taraka', 'Tubaran', 'Tugaya', 'Wao'),
        'Leyte' => array('Tacloban City', 'Ormoc City', 'Baybay City', 'Abuyog', 'Alangalang', 'Albuera', 'Babatngon', 'Barugo', 'Bato', 'Burauen', 'Calubian', 'Capoocan', 'Carigara', 'Dagami', 'Dulag', 'Hilongos', 'Hindang', 'Inopacan', 'Isabel', 'Jaro', 'Javier', 'Julita', 'Kananga', 'La Paz', 'Leyte', 'MacArthur', 'Mahaplag', 'Matag-ob', 'Matalom', 'Mayorga', 'Merida', 'Palo', 'Palompon', 'Pastrana', 'San Isidro', 'San Miguel', 'Santa Fe', 'Tabango', 'Tabontabon', 'Tanauan', 'Tolosa', 'Tunga', 'Villaba'),
        'Maguindanao del Norte' => array('Datu Odin Sinsuat', 'Barira', 'Buldon', 'Kabuntalan', 'Matanog', 'Northern Kabuntalan', 'Parang', 'Sultan Kudarat', 'Sultan Mastura', 'Upi'),
        'Maguindanao del Sur' => array('Buluan', 'Ampatuan', 'Datu Abdullah Sangki', 'Datu Anggal Midtimbang', 'Datu Blah T. Sinsuat', 'Datu Hoffer Ampatuan', 'Datu Montawal', 'Datu Paglas', 'Datu Piang', 'Datu Saudi-Ampatuan', 'Datu Unsay', 'General Salipada K. Pendatun', 'Guindulungan', 'Mamasapano', 'Mangudadatu', 'Pagalungan', 'Paglat', 'Pandag', 'Rajah Buayan', 'Shariff Aguak', 'Shariff Saydona Mustapha', 'South Upi', 'Sultan sa Barongis', 'Talayan', 'Talitay'),
        'Marinduque' => array('Boac', 'Buenavista', 'Gasan', 'Mogpog', 'Santa Cruz', 'Torrijos'),
        'Masbate' => array('Masbate City', 'Aroroy', 'Baleno', 'Balud', 'Batuan', 'Cataingan', 'Cawayan', 'Claveria', 'Dimasalang', 'Esperanza', 'Mandaon', 'Milagros', 'Mobo', 'Monreal', 'Palanas', 'Pio V. Corpuz', 'Placer', 'San Fernando', 'San Jacinto', 'San Pascual', 'Uson'),
        'Metro Manila' => array('City of Manila', 'Caloocan City', 'Las Piñas City', 'Makati City', 'Malabon City', 'Mandaluyong City', 'Marikina City', 'Muntinlupa City', 'Navotas City', 'Parañaque City', 'Pasay City', 'Pasig City', 'Quezon City', 'San Juan City', 'Taguig City', 'Valenzuela City', 'Pateros'),
        'Misamis Occidental' => array('Oroquieta City', 'Ozamiz City', 'Tangub City', 'Aloran', 'Baliangao', 'Bonifacio', 'Calamba', 'Clarin', 'Concepcion', 'Don Victoriano Chiongbian', 'Jimenez', 'Lopez Jaena', 'Panaon', 'Plaridel', 'Sapang Dalaga', 'Sinacaban', 'Tudela'),
        'Misamis Oriental' => array('Cagayan de Oro City', 'El Salvador City', 'Gingoog City', 'Alubijid', 'Balingasag', 'Balingoan', 'Binuangan', 'Claveria', 'Gitagum', 'Initao', 'Jasaan', 'Kinoguitan', 'Lagonglong', 'Laguindingan', 'Libertad', 'Lugait', 'Magsaysay', 'Manticao', 'Medina', 'Naawan', 'Opol', 'Salay', 'Sugbongcogon', 'Tagoloan', 'Talisayan', 'Villanueva'),
        'Mountain Province' => array('Bontoc', 'Barlig', 'Bauko', 'Besao', 'Natonin', 'Paracelis', 'Sabangan', 'Sadanga', 'Sagada', 'Tadian'),
        'Negros Occidental' => array('Bacolod City', 'Bago City', 'Cadiz City', 'Escalante City', 'Himamaylan City', 'Kabankalan City', 'La Carlota City', 'Sagay City', 'San Carlos City', 'Silay City', 'Sipalay City', 'Talisay City', 'Victorias City', 'Binalbagan', 'Calatrava', 'Candoni', 'Cauayan', 'Enrique B. Magalona', 'Hinigaran', 'Hinoba-an', 'Ilog', 'Isabela', 'La Castellana', 'Manapla', 'Moises Padilla', 'Murcia', 'Pontevedra', 'Pulupandan', 'Salvador Benedicto', 'San Enrique', 'Toboso', 'Valladolid'),
        'Negros Oriental' => array('Dumaguete City', 'Bais City', 'Bayawan City', 'Canlaon City', 'Guihulngan City', 'Tanjay City', 'Amlan', 'Ayungon', 'Bacong', 'Basay', 'Bindoy', 'Dauin', 'Jimalalud', 'La Libertad', 'Mabinay', 'Manjuyod', 'Pamplona', 'San Jose', 'Santa Catalina', 'Siaton', 'Sibulan', 'Tayasan', 'Valencia', 'Vallehermoso', 'Zamboanguita'),
        'Northern Samar' => array('Catarman', 'Allen', 'Biri', 'Bobon', 'Capul', 'Catubig', 'Gamay', 'Laoang', 'Lapinig', 'Las Navas', 'Lavezares', 'Lope de Vega', 'Mapanas', 'Mondragon', 'Palapag', 'Pambujan', 'Rosario', 'San Antonio', 'San Isidro', 'San Jose', 'San Roque', 'San Vicente', 'Silvino Lobos', 'Victoria'),
        'Nueva Ecija' => array('Cabanatuan City', 'Gapan City', 'Muñoz City', 'Palayan City', 'San Jose City', 'Aliaga', 'Bongabon', 'Cabiao', 'Carranglan', 'Cuyapo', 'Gabaldon', 'General Mamerto Natividad', 'General Tinio', 'Guimba', 'Jaen', 'Laur', 'Licab', 'Llanera', 'Lupao', 'Nampicuan', 'Pantabangan', 'Peñaranda', 'Quezon', 'Rizal', 'San Antonio', 'San Isidro', 'San Leonardo', 'Santa Rosa', 'Santo Domingo', 'Talavera', 'Talugtug', 'Zaragoza'),
        'Nueva Vizcaya' => array('Bayombong', 'Alfonso Castañeda', 'Ambaguio', 'Aritao', 'Bagabag', 'Bambang', 'Diadi', 'Dupax del Norte', 'Dupax del Sur', 'Kasibu', 'Kayapa', 'Quezon', 'Santa Fe', 'Solano', 'Villaverde'),
        'Occidental Mindoro' => array('Mamburao', 'Abra de Ilog', 'Calintaan', 'Looc', 'Lubang', 'Magdiwang', 'Paluan', 'Rizal', 'Sablayan', 'San Jose', 'Santa Cruz'),
        'Oriental Mindoro' => array('Calapan City', 'Baco', 'Bansud', 'Bongabong', 'Bulalacao', 'Gloria', 'Mansalay', 'Naujan', 'Pinamalayan', 'Pola', 'Puerto Galera', 'Roxas', 'San Teodoro', 'Socorro', 'Victoria'),
        'Palawan' => array('Puerto Princesa City', 'Aborlan', 'Agutaya', 'Araceli', 'Balabac', 'Bataraza', 'Brooke\'s Point', 'Busuanga', 'Cagayancillo', 'Coron', 'Culion', 'Cuyo', 'Dumaran', 'El Nido', 'Kalayaan', 'Linapacan', 'Magsaysay', 'Narra', 'Quezon', 'Rizal', 'Roxas', 'San Vicente', 'Sofronio Española', 'Taytay'),
        'Pampanga' => array('Angeles City', 'San Fernando City', 'Mabalacat City', 'Apalit', 'Arayat', 'Bacolor', 'Candaba', 'Floridablanca', 'Guagua', 'Lubao', 'Macabebe', 'Magalang', 'Masantol', 'Mexico', 'Minalin', 'Porac', 'San Luis', 'San Simon', 'Santa Ana', 'Santa Rita', 'Santo Tomas', 'Sasmuan'),
        'Pangasinan' => array('Dagupan City', 'San Carlos City', 'Urdaneta City', 'Alaminos City', 'Agno', 'Aguilar', 'Alcala', 'Anda', 'Asingan', 'Balungao', 'Bani', 'Basista', 'Bautista', 'Bayambang', 'Binalonan', 'Binmaley', 'Bolinao', 'Bugallon', 'Burgos', 'Calasiao', 'Dasol', 'Infanta', 'Labrador', 'Laoac', 'Lingayen', 'Mabini', 'Malasiqui', 'Manaoag', 'Mangaldan', 'Mangatarem', 'Mapandan', 'Natividad', 'Pozorrubio', 'Rosales', 'San Fabian', 'San Jacinto', 'San Manuel', 'San Nicolas', 'San Quintin', 'Santa Barbara', 'Santa Maria', 'Santo Tomas', 'Sison', 'Sual', 'Tayug', 'Umingan', 'Urbiztondo', 'Villasis'),
        'Quezon' => array('Lucena City', 'Tayabas City', 'Agdangan', 'Alabat', 'Atimonan', 'Buenavista', 'Burdeos', 'Calauag', 'Candelaria', 'Catanauan', 'Dolores', 'General Luna', 'General Nakar', 'Guinayangan', 'Gumaca', 'Infanta', 'Jomalig', 'Lopez', 'Lucban', 'Macalelon', 'Mauban', 'Mulanay', 'Padre Burgos', 'Pagbilao', 'Panukulan', 'Patnanungan', 'Perez', 'Pitogo', 'Plaridel', 'Polillo', 'Quezon', 'Real', 'Sampaloc', 'San Andres', 'San Antonio', 'San Francisco', 'San Narciso', 'Sariaya', 'Tagkawayan', 'Tiaong', 'Unisan'),
        'Quirino' => array('Cabarroguis', 'Aglipay', 'Diffun', 'Maddela', 'Nagtipunan', 'Saguday'),
        'Rizal' => array('Antipolo City', 'Angono', 'Baras', 'Binangonan', 'Cainta', 'Cardona', 'Jalajala', 'Morong', 'Pililla', 'Rodriguez', 'San Mateo', 'Tanay', 'Taytay', 'Teresa'),
        'Romblon' => array('Romblon', 'Alcantara', 'Banton', 'Cajidiocan', 'Calatrava', 'Concepcion', 'Corcuera', 'Ferrol', 'Looc', 'Magdiwang', 'Odiongan', 'San Agustin', 'San Andres', 'San Fernando', 'San Jose', 'Santa Fe', 'Santa Maria'),
        'Samar' => array('Catbalogan City', 'Calbayog City', 'Almagro', 'Basey', 'Calbiga', 'Daram', 'Gandara', 'Hinabangan', 'Jiabong', 'Marabut', 'Matuguinao', 'Motiong', 'Pagsanghan', 'Paranas', 'Pinabacdao', 'San Jorge', 'San Jose de Buan', 'San Sebastian', 'Santa Margarita', 'Santa Rita', 'Santo Niño', 'Tagapul-an', 'Talalora', 'Tarangnan', 'Villareal', 'Zumarraga'),
        'Sarangani' => array('Alabel', 'Glan', 'Kiamba', 'Maasim', 'Maitum', 'Malapatan', 'Malungon'),
        'Siquijor' => array('Siquijor', 'Enrique Villanueva', 'Larena', 'Lazi', 'Maria', 'San Juan'),
        'Sorsogon' => array('Sorsogon City', 'Barcelona', 'Bulan', 'Bulusan', 'Casiguran', 'Castilla', 'Donsol', 'Gubat', 'Irosin', 'Juban', 'Magallanes', 'Matnog', 'Pilar', 'Prieto Diaz', 'Santa Magdalena'),
        'South Cotabato' => array('General Santos City', 'Koronadal City', 'Banga', 'Lake Sebu', 'Norala', 'Polomolok', 'Santo Niño', 'Surallah', 'T\'boli', 'Tampakan', 'Tantangan', 'Tupi'),
        'Southern Leyte' => array('Maasin City', 'Anahawan', 'Bontoc', 'Hinunangan', 'Hinundayan', 'Libagon', 'Liloan', 'Limasawa', 'Macrohon', 'Malitbog', 'Padre Burgos', 'Pintuyan', 'Saint Bernard', 'San Francisco', 'San Juan', 'San Ricardo', 'Silago', 'Sogod', 'Tomas Oppus'),
        'Sultan Kudarat' => array('Tacurong City', 'Bagumbong', 'Esperanza', 'Isulan', 'Kalamansig', 'Lambayong', 'Lebak', 'Lutayan', 'Palimbang', 'President Quirino', 'Senator Ninoy Aquino'),
        'Sulu' => array('Jolo', 'Banguingui', 'Hadji Panglima Tahil', 'Indanan', 'Kalingalan Caluang', 'Lugus', 'Luuk', 'Maimbung', 'Old Panamao', 'Omar', 'Pandami', 'Panglima Estino', 'Pangutaran', 'Parang', 'Pata', 'Patikul', 'Siasi', 'Talipao', 'Tapul'),
        'Surigao del Norte' => array('Surigao City', 'Alegria', 'Bacuag', 'Burgos', 'Claver', 'Dapa', 'Del Carmen', 'General Luna', 'Gigaquit', 'Mainit', 'Malimono', 'Pilar', 'Placer', 'San Benito', 'San Francisco', 'San Isidro', 'Santa Monica', 'Sison', 'Socorro', 'Tagana-an', 'Tubod'),
        'Surigao del Sur' => array('Tandag City', 'Bislig City', 'Barobo', 'Bayabas', 'Cagwait', 'Cantilan', 'Carmen', 'Carrascal', 'Cortes', 'Hinatuan', 'Lanuza', 'Lianga', 'Lingig', 'Madrid', 'Marihatag', 'San Agustin', 'San Miguel', 'Tagbina', 'Tago'),
        'Tarlac' => array('Tarlac City', 'Anao', 'Bamban', 'Camiling', 'Capas', 'Concepcion', 'Gerona', 'La Paz', 'Mayantoc', 'Moncada', 'Paniqui', 'Pura', 'Ramos', 'San Clemente', 'San Jose', 'San Manuel', 'Santa Ignacia', 'Victoria'),
        'Tawi-Tawi' => array('Bongao', 'Languyan', 'Mapun', 'Panglima Sugala', 'Sapa-Sapa', 'Sibutu', 'Simunul', 'Sitangkai', 'South Ubian', 'Tandubas', 'Turtle Islands'),
        'Zambales' => array('Olongapo City', 'Botolan', 'Cabangan', 'Candelaria', 'Castillejos', 'Iba', 'Masinloc', 'Palauig', 'San Antonio', 'San Felipe', 'San Marcelino', 'San Narciso', 'Santa Cruz', 'Subic'),
        'Zamboanga del Norte' => array('Dipolog City', 'Dapitan City', 'Bacungan', 'Baliguian', 'Godod', 'Gutalac', 'Jose Dalman', 'Kalawit', 'Katipunan', 'La Libertad', 'Labason', 'Leon B. Postigo', 'Liloy', 'Manukan', 'Mutia', 'Piñan', 'Polanco', 'President Manuel A. Roxas', 'Rizal', 'Salug', 'Sergio Osmeña Sr.', 'Siayan', 'Sibuco', 'Sibutad', 'Sindangan', 'Siocon', 'Sirawai', 'Tampilisan'),
        'Zamboanga del Sur' => array('Pagadian City', 'Zamboanga City', 'Aurora', 'Bayog', 'Dimataling', 'Dinas', 'Dumalinao', 'Dumingag', 'Guipos', 'Josefina', 'Kumalarang', 'Labangan', 'Lakewood', 'Lapuyan', 'Mahayag', 'Margosatubig', 'Midsalip', 'Molave', 'Pitogo', 'Ramon Magsaysay', 'San Miguel', 'San Pablo', 'Sominot', 'Tabina', 'Tambulig', 'Tigbao', 'Tukuran', 'Vincenzo A. Sagun'),
        'Zamboanga Sibugay' => array('Ipil', 'Alicia', 'Buug', 'Diplahan', 'Imelda', 'Kabasalan', 'Mabuhay', 'Malangas', 'Naga', 'Olutanga', 'Payao', 'Roseller Lim', 'Siay', 'Talusan', 'Titay', 'Tungawan')
    );
}

function fh_geo_get_countries($conn) {
    fh_geo_ensure_seeded($conn);
    $list = array();
    $sql = $conn->query("SELECT country_id, country_name FROM geo_countries ORDER BY CASE WHEN country_name = 'Philippines' THEN 0 ELSE 1 END, country_name ASC");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function fh_geo_get_provinces($conn, $countryId) {
    fh_geo_ensure_seeded($conn);
    $countryId = (int)$countryId;
    $list = array();
    if ($countryId <= 0) {
        return $list;
    }
    $sql = $conn->query("SELECT province_id, province_name FROM geo_provinces WHERE country_id = $countryId ORDER BY province_name ASC");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function fh_geo_get_towns($conn, $provinceId) {
    fh_geo_ensure_seeded($conn);
    fh_geo_sync_zipcodes($conn);
    $provinceId = (int)$provinceId;
    $list = array();
    if ($provinceId <= 0) {
        return $list;
    }
    $sql = $conn->query("SELECT town_id, town_name, zipcode FROM geo_towns WHERE province_id = $provinceId ORDER BY town_name ASC");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function fh_geo_options_html($rows, $valueKey, $labelKey, $selected = '', $emptyLabel = 'Select...') {
    $html = '<option value="">' . htmlspecialchars($emptyLabel) . '</option>';
    foreach ($rows as $row) {
        $val = $row[$valueKey];
        $label = $row[$labelKey];
        $sel = ((string)$val === (string)$selected || (string)$label === (string)$selected) ? ' selected' : '';
        $zip = isset($row['zipcode']) ? $row['zipcode'] : '';
        $zipAttr = $zip !== '' && $zip !== null ? ' data-zipcode="' . htmlspecialchars($zip) . '"' : '';
        $html .= '<option value="' . htmlspecialchars($label) . '" data-id="' . htmlspecialchars($val) . '"' . $zipAttr . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
    return $html;
}

function fh_geo_default_zipcode($conn, $provinceName, $townName) {
    fh_geo_ensure_seeded($conn);
    fh_geo_sync_zipcodes($conn);
    $look = fh_geo_lookup_zip($provinceName, $townName);
    if ($look !== '') {
        return $look;
    }
    $p = $conn->real_escape_string($provinceName);
    $t = $conn->real_escape_string($townName);
    $sql = $conn->query(
        "SELECT gt.zipcode
         FROM geo_towns gt
         INNER JOIN geo_provinces gp ON gp.province_id = gt.province_id
         WHERE gp.province_name = '$p' AND gt.town_name = '$t'
         LIMIT 1"
    );
    if ($sql && ($row = $sql->fetch_assoc()) && !empty($row['zipcode'])) {
        return $row['zipcode'];
    }
    return '';
}

function fh_geo_barangay_map() {
    // Key: Province|Town / City => list of barangays
    return array(
        'Bohol|Tagbilaran City' => array(
            'Bool', 'Booy', 'Cabawan', 'Cogon', 'Dampas', 'Dao', 'Manga', 'Mansasa',
            'Poblacion I', 'Poblacion II', 'Poblacion III', 'San Isidro', 'Taloto', 'Tiptip', 'Ubujan'
        ),
        'Bohol|Panglao' => array(
            'Bil-isan', 'Bolod', 'Danao', 'Doljo', 'Libaong', 'Looc', 'Lourdes', 'Poblacion', 'Tangnan', 'Tawala'
        ),
        'Bohol|Dauis' => array(
            'Biking', 'Bingag', 'Catarman', 'Dao', 'Mariveles', 'Mayacabac', 'Poblacion', 'San Isidro', 'Songculan', 'Tabalong', 'Tinago', 'Totolan'
        ),
        'Bohol|Baclayon' => array(
            'Buenasuerte', 'Cambanac', 'Dasitam', 'Guiwanon', 'Landican', 'Laya', 'Loyola Norte', 'Loyola Sur',
            'Poblacion', 'San Isidro', 'San Roque', 'Talisay', 'Tiwi'
        ),
        'Bohol|Loboc' => array(
            'Agape', 'Alegria', 'Bagisan', 'Baguan', 'Bauhugan', 'Bitoon', 'Bonola', 'Buenavista',
            'Bugho', 'Calunasan', 'Camayaan', 'Cambance', 'Candabong', 'Candasag', 'Candulinan',
            'Canlasid', 'Cabadiangan', 'Gondola', 'Gotojon', 'Jimilian', 'Oy', 'Poblacion Ondol',
            'Poblacion Sawang', 'Quinapon-an', 'Tongonan', 'Ugpong', 'Valladolid'
        ),
        'Bohol|Cortes' => array(
            'De la Paz', 'Fatima', 'Loreto', 'Lourdes', 'Malayo Norte', 'Malayo Sur', 'Monserrat',
            'New Lourdes', 'Patrocinio', 'Poblacion', 'Poblacion Upper', 'Rosario', 'Salvador',
            'San Roque', 'Upper de la Paz'
        ),
        'Bohol|Corella' => array(
            'Anislag', 'Canangca-an', 'Canapnapan', 'Cancatac', 'Pandol', 'Poblacion', 'Sambog', 'Tanday'
        ),
        'Bohol|Maribojoc' => array(
            'Agahay', 'Aliguay', 'Anislag', 'Bayacabac', 'Bood', 'Busao', 'Cabawan', 'Candavid',
            'Dipatlong', 'Guiwanon', 'Jandig', 'Lagtangon', 'Lincod', 'Pagnitoan', 'Poblacion',
            'Punsod', 'Punta Cruz', 'San Isidro', 'San Roque', 'San Vicente', 'Tinibgan', 'Toril'
        ),
        'Bohol|Antequera' => array(
            'Angilan', 'Bantolinao', 'Bicahan', 'Bitaugan', 'Bungahan', 'Canlaas', 'Cansibuan',
            'Can-omay', 'Celing', 'Danao', 'Danicop', 'Mag-aso', 'Poblacion', 'Quinapon-an',
            'Santo Rosario', 'Tabuan', 'Tagubaas', 'Tupas', 'Ubojan', 'Viga', 'Villa Aurora'
        ),
        'Bohol|Loay' => array(
            'Agape', 'Alejawan Norte', 'Alejawan Sur', 'Bonbon', 'Botoc Occidental', 'Botoc Oriental',
            'Calvario', 'Concepcion', 'Hinawanan', 'Las Salinas Norte', 'Las Salinas Sur', 'Pueblo Nuevo',
            'Poblacion Ibabao', 'Poblacion Ubos', 'Tayong Occidental', 'Tayong Oriental', 'Tocdog Dacu',
            'Tocdog Ilaya', 'Villalimpia', 'Yanangan'
        ),
        'Bohol|Alburquerque' => array(
            'Bahi', 'Basacdacu', 'Cantiguib', 'Dangay', 'East Poblacion', 'Ponong', 'San Agustin',
            'Santa Filomena', 'Tagbuane', 'Toril', 'West Poblacion'
        ),
        'Bohol|Sikatuna' => array(
            'Abucay Norte', 'Abucay Sur', 'Badiang', 'Bahaybahay', 'Cambuac Norte', 'Cambuac Sur',
            'Canagong', 'Libjo', 'Poblacion I', 'Poblacion II'
        ),
        'Bohol|Tubigon' => array(
            'Asin', 'Bagtason', 'Batasan', 'Bilangbilangan', 'Bosongon', 'Buenavista', 'Bunacan',
            'Cabulihan', 'Cahayag', 'Cawayanan', 'Centro', 'Genonocan', 'Ilihan Norte', 'Ilihan Sur',
            'Libertad', 'Macaas', 'Matabao', 'Panadtaran', 'Panaytayon', 'Pandan', 'Pangapasan',
            'Pinayagan Norte', 'Pinayagan Sur', 'Pooc Occidental', 'Pooc Oriental', 'Potohan',
            'Talenceras', 'Tan-awan', 'Tinangnan', 'Ubayon', 'Ubojan'
        ),
        'Bohol|Ubay' => array(
            'Achila', 'Bay-ang', 'Benliw', 'Biabas', 'Bongbong', 'Bood', 'Buenavista', 'Bulilis',
            'Cagting', 'Calanggaman', 'California', 'Camali-an', 'Camambugan', 'Casate', 'Cuya',
            'Fatima', 'Guintabo-an', 'Hambabauran', 'Humayhumay', 'Ilihan', 'Imelda', 'Juagdan',
            'Katarungan', 'Lomangog', 'Los Angeles', 'Pag-asa', 'Pangpang', 'Poblacion', 'San Francisco',
            'San Isidro', 'San Pascual', 'San Vicente', 'Sentinila', 'Sinandigan', 'Tapal', 'Tapon',
            'Tintinan', 'Tipolo', 'Tubog', 'Tuboran', 'Union', 'Villa Teresita'
        )
    );
}

function fh_geo_sync_barangays($conn) {
    static $synced = false;
    if ($synced) {
        return;
    }
    $synced = true;

    $check = @$conn->query("SHOW TABLES LIKE 'geo_barangays'");
    if (!$check || $check->num_rows === 0) {
        return;
    }

    $map = fh_geo_barangay_map();
    foreach ($map as $key => $barangays) {
        $parts = explode('|', $key, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $province = $conn->real_escape_string($parts[0]);
        $town = $conn->real_escape_string($parts[1]);
        $sql = $conn->query(
            "SELECT gt.town_id
             FROM geo_towns gt
             INNER JOIN geo_provinces gp ON gp.province_id = gt.province_id
             WHERE gp.province_name = '$province' AND gt.town_name = '$town'
             LIMIT 1"
        );
        if (!$sql || !($row = $sql->fetch_assoc())) {
            continue;
        }
        $townId = (int)$row['town_id'];
        foreach ($barangays as $brgy) {
            $bName = $conn->real_escape_string($brgy);
            $conn->query("INSERT IGNORE INTO geo_barangays (town_id, barangay_name) VALUES ($townId, '$bName')");
        }
    }
}

function fh_geo_get_barangays($conn, $townId) {
    fh_geo_ensure_seeded($conn);
    fh_geo_sync_barangays($conn);
    $townId = (int)$townId;
    $list = array();
    if ($townId <= 0) {
        return $list;
    }
    $check = @$conn->query("SHOW TABLES LIKE 'geo_barangays'");
    if (!$check || $check->num_rows === 0) {
        return $list;
    }
    $sql = $conn->query("SELECT barangay_id, barangay_name FROM geo_barangays WHERE town_id = $townId ORDER BY barangay_name ASC");
    if ($sql) {
        while ($row = $sql->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function fh_geo_find_town_id($conn, $provinceName, $townName) {
    fh_geo_ensure_seeded($conn);
    $p = $conn->real_escape_string($provinceName);
    $t = $conn->real_escape_string($townName);
    $sql = $conn->query(
        "SELECT gt.town_id
         FROM geo_towns gt
         INNER JOIN geo_provinces gp ON gp.province_id = gt.province_id
         WHERE gp.province_name = '$p' AND gt.town_name = '$t'
         LIMIT 1"
    );
    if ($sql && ($row = $sql->fetch_assoc())) {
        return (int)$row['town_id'];
    }
    return 0;
}

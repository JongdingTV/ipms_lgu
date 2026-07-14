<?php
/**
 * Quezon City congressional districts and their barangays.
 *
 * Single source of truth used by:
 *  - citizen/dashboard.php   (renders the district/barangay selects + map data)
 *  - citizen/api/submit-feedback.php (server-side validation of the pair)
 *
 * Each barangay entry:
 *  - name : canonical name shown to users
 *  - alt  : common alternate name(s), shown as a hint ('' if none)
 *  - geo  : the adm4_en value in assets/data/qc-barangays.geojson when it
 *           differs in spelling from `name` (null = identical), so the map
 *           can match polygons to list entries.
 */

function qcDistricts(): array
{
    static $districts = null;
    if ($districts !== null) {
        return $districts;
    }

    $districts = [
        'District 1' => [
            ['name' => 'Alicia', 'alt' => 'Bago Bantay', 'geo' => null],
            ['name' => 'Bagong Pag-asa', 'alt' => 'North-EDSA, Diliman (southern part), Triangle Park (southern triangle)', 'geo' => null],
            ['name' => 'Bahay Toro', 'alt' => 'Project 8', 'geo' => null],
            ['name' => 'Balingasa', 'alt' => 'Balintawak, Cloverleaf', 'geo' => null],
            ['name' => 'Bungad', 'alt' => 'Project 7', 'geo' => null],
            ['name' => 'Damar', 'alt' => '', 'geo' => null],
            ['name' => 'Damayan', 'alt' => 'San Francisco del Monte, SFDM, Frisco', 'geo' => null],
            ['name' => 'Del Monte', 'alt' => 'San Francisco del Monte, SFDM, Frisco', 'geo' => null],
            ['name' => 'Katipunan', 'alt' => 'Muñoz', 'geo' => null],
            ['name' => 'Lourdes', 'alt' => 'Sta. Mesa Heights', 'geo' => null],
            ['name' => 'Maharlika', 'alt' => 'Sta. Mesa Heights', 'geo' => null],
            ['name' => 'Manresa', 'alt' => '', 'geo' => null],
            ['name' => 'Mariblo', 'alt' => 'San Francisco del Monte, SFDM, Frisco', 'geo' => null],
            ['name' => 'Masambong', 'alt' => '', 'geo' => null],
            ['name' => 'N.S. Amoranto (Gintong Silahis)', 'alt' => 'La Loma', 'geo' => 'N.S. Amoranto'],
            ['name' => 'Nayong Kanluran', 'alt' => '', 'geo' => null],
            ['name' => 'Paang Bundok', 'alt' => 'La Loma', 'geo' => null],
            ['name' => 'Pag-ibig sa Nayon', 'alt' => 'Balintawak', 'geo' => 'Pag-ibig Sa Nayon'],
            ['name' => 'Paltok', 'alt' => 'San Francisco del Monte, SFDM, Frisco', 'geo' => null],
            ['name' => 'Paraiso', 'alt' => 'San Francisco del Monte, SFDM, Frisco', 'geo' => null],
            ['name' => 'Phil-Am', 'alt' => 'West Triangle', 'geo' => null],
            ['name' => 'Project 6', 'alt' => 'Diliman (southeast quarter), Triangle Park (southern half)', 'geo' => null],
            ['name' => 'Ramon Magsaysay', 'alt' => 'Bago Bantay', 'geo' => null],
            ['name' => 'Saint Peter', 'alt' => 'Sta. Mesa Heights', 'geo' => null],
            ['name' => 'Salvacion', 'alt' => 'La Loma', 'geo' => null],
            ['name' => 'San Antonio', 'alt' => 'San Francisco del Monte, SFDM, Frisco', 'geo' => null],
            ['name' => 'San Isidro Labrador', 'alt' => 'La Loma', 'geo' => null],
            ['name' => 'San Jose', 'alt' => 'La Loma', 'geo' => null],
            ['name' => 'Santa Cruz', 'alt' => 'Pantranco, Heroes Hill', 'geo' => null],
            ['name' => 'Santa Teresita', 'alt' => 'Sta. Mesa Heights', 'geo' => null],
            ['name' => 'Sto. Cristo', 'alt' => 'Bago Bantay', 'geo' => 'Santo Cristo'],
            ['name' => 'Santo Domingo (Matalahib)', 'alt' => '', 'geo' => 'Santo Domingo'],
            ['name' => 'Siena', 'alt' => '', 'geo' => 'Sienna'],
            ['name' => 'Talayan', 'alt' => '', 'geo' => null],
            ['name' => 'Vasra', 'alt' => 'Diliman (mostly)', 'geo' => null],
            ['name' => 'Veterans Village', 'alt' => 'Project 7, Muñoz', 'geo' => null],
            ['name' => 'West Triangle', 'alt' => '', 'geo' => null],
        ],
        'District 2' => [
            ['name' => 'Bagong Silangan', 'alt' => '', 'geo' => null],
            ['name' => 'Batasan Hills', 'alt' => 'Constitution Hills', 'geo' => null],
            ['name' => 'Commonwealth', 'alt' => 'Manggahan', 'geo' => null],
            ['name' => 'Holy Spirit', 'alt' => 'Don Antonio', 'geo' => null],
            ['name' => 'Payatas', 'alt' => '', 'geo' => null],
        ],
        'District 3' => [
            ['name' => 'Amihan', 'alt' => 'Project 3', 'geo' => null],
            ['name' => 'Bagumbayan', 'alt' => 'Eastwood', 'geo' => null],
            ['name' => 'Bagumbuhay', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Bayanihan', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Blue Ridge A', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Blue Ridge B', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Camp Aguinaldo', 'alt' => 'Armed Forces (AFP), Camp General Emilio Aguinaldo', 'geo' => null],
            ['name' => 'Claro (Quirino 3-B)', 'alt' => 'Project 3', 'geo' => 'Claro'],
            ['name' => 'Dioquino Zobel', 'alt' => '', 'geo' => null],
            ['name' => 'Duyan-duyan', 'alt' => 'Project 3', 'geo' => null],
            ['name' => 'E. Rodriguez', 'alt' => 'Project 5, Cubao', 'geo' => null],
            ['name' => 'East Kamias', 'alt' => 'Project 1', 'geo' => null],
            ['name' => 'Escopa I', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Escopa II', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Escopa III', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Escopa IV', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Libis', 'alt' => 'Eastwood', 'geo' => null],
            ['name' => 'Loyola Heights', 'alt' => 'Katipunan', 'geo' => null],
            ['name' => 'Mangga', 'alt' => 'Cubao', 'geo' => null],
            ['name' => 'Marilag', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Masagana', 'alt' => 'Jacobo Zobel', 'geo' => null],
            ['name' => 'Matandang Balara', 'alt' => 'Old Balara', 'geo' => null],
            ['name' => 'Milagrosa', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Pansol', 'alt' => 'Balara', 'geo' => null],
            ['name' => 'Quirino 2-A', 'alt' => 'Project 2, Anonas', 'geo' => null],
            ['name' => 'Quirino 2-B', 'alt' => 'Project 2, Anonas', 'geo' => null],
            ['name' => 'Quirino 2-C', 'alt' => 'Project 2, Anonas', 'geo' => null],
            ['name' => 'Quirino 3-A', 'alt' => 'Project 3, Anonas', 'geo' => null],
            ['name' => 'St. Ignatius', 'alt' => '', 'geo' => 'Saint Ignatius'],
            ['name' => 'San Roque', 'alt' => 'Cubao', 'geo' => null],
            ['name' => 'Silangan', 'alt' => 'Cubao', 'geo' => null],
            ['name' => 'Socorro', 'alt' => 'Cubao, Araneta City', 'geo' => null],
            ['name' => 'Tagumpay', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'Ugong Norte', 'alt' => 'Green Meadows, Corinthian, Ortigas (southernmost)', 'geo' => null],
            ['name' => 'Villa Maria Clara', 'alt' => 'Project 4', 'geo' => null],
            ['name' => 'West Kamias', 'alt' => 'Project 5, Kamias', 'geo' => null],
            ['name' => 'White Plains', 'alt' => '', 'geo' => null],
        ],
        'District 4' => [
            ['name' => 'Bagong Lipunan ng Crame', 'alt' => 'Camp Crame, PNP', 'geo' => 'Bagong Lipunan Ng Crame'],
            ['name' => 'Botocan', 'alt' => 'Diliman (northern half)', 'geo' => null],
            ['name' => 'Central', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'Damayang Lagi', 'alt' => 'New Manila', 'geo' => null],
            ['name' => 'Don Manuel', 'alt' => 'Galas', 'geo' => null],
            ['name' => 'Doña Aurora', 'alt' => 'Galas', 'geo' => 'Aurora'],
            ['name' => 'Doña Imelda', 'alt' => 'Galas, Sta. Mesa', 'geo' => null],
            ['name' => 'Doña Josefa', 'alt' => 'Galas', 'geo' => null],
            ['name' => 'Horseshoe', 'alt' => '', 'geo' => null],
            ['name' => 'Immaculate Concepcion', 'alt' => 'Cubao', 'geo' => null],
            ['name' => 'Kalusugan', 'alt' => "St. Luke's", 'geo' => null],
            ['name' => 'Kamuning', 'alt' => '', 'geo' => null],
            ['name' => 'Kaunlaran', 'alt' => 'Cubao', 'geo' => null],
            ['name' => 'Kristong Hari', 'alt' => '', 'geo' => null],
            ['name' => 'Krus na Ligas', 'alt' => 'Diliman', 'geo' => 'Krus Na Ligas'],
            ['name' => 'Laging Handa', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'Malaya', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'Mariana', 'alt' => 'New Manila', 'geo' => null],
            ['name' => 'Obrero', 'alt' => 'Diliman (northern half), Project 1 (southern half)', 'geo' => null],
            ['name' => 'Old Capitol Site', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'Paligsahan', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'Pinagkaisahan', 'alt' => 'Cubao', 'geo' => null],
            ['name' => 'Pinyahan', 'alt' => 'Diliman, Triangle Park (northern triangle)', 'geo' => null],
            ['name' => 'Roxas', 'alt' => 'Project 1', 'geo' => null],
            ['name' => 'Sacred Heart', 'alt' => 'Kamuning', 'geo' => null],
            ['name' => 'San Isidro Galas', 'alt' => 'Galas', 'geo' => 'San Isidro'],
            ['name' => 'San Martin de Porres', 'alt' => 'Cubao', 'geo' => 'San Martin De Porres'],
            ['name' => 'San Vicente', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'Santol', 'alt' => '', 'geo' => null],
            ['name' => 'Sikatuna Village', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'South Triangle', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'Sto. Niño', 'alt' => 'Galas', 'geo' => 'Santo Niño'],
            ['name' => 'Tatalon', 'alt' => '', 'geo' => null],
            ['name' => "Teacher's Village East", 'alt' => 'Diliman', 'geo' => 'Teachers Village East'],
            ['name' => "Teacher's Village West", 'alt' => 'Diliman', 'geo' => 'Teachers Village West'],
            ['name' => 'U.P. Campus', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'U.P. Village', 'alt' => 'Diliman', 'geo' => null],
            ['name' => 'Valencia', 'alt' => 'Gilmore Ave, N. Domingo Ave.', 'geo' => null],
        ],
        'District 5' => [
            ['name' => 'Bagbag', 'alt' => 'Novaliches District', 'geo' => null],
            ['name' => 'Capri', 'alt' => 'Novaliches District', 'geo' => null],
            ['name' => 'Fairview', 'alt' => 'La Mesa, Novaliches District', 'geo' => null],
            ['name' => 'Greater Lagro', 'alt' => 'Lagro, Novaliches District', 'geo' => null],
            ['name' => 'Gulod', 'alt' => 'Novaliches District', 'geo' => null],
            ['name' => 'Kaligayahan', 'alt' => 'Novaliches District, Zabarte', 'geo' => null],
            ['name' => 'Nagkaisang Nayon', 'alt' => 'Novaliches District, General Luis', 'geo' => null],
            ['name' => 'North Fairview', 'alt' => 'Novaliches District', 'geo' => null],
            ['name' => 'Novaliches Proper', 'alt' => 'Novaliches Bayan', 'geo' => null],
            ['name' => 'Pasong Putik Proper', 'alt' => 'Novaliches District', 'geo' => null],
            ['name' => 'San Agustin', 'alt' => 'Novaliches District, Susano', 'geo' => null],
            ['name' => 'San Bartolome', 'alt' => 'Novaliches District, Holy Cross', 'geo' => null],
            ['name' => 'Sta. Lucia', 'alt' => 'Novaliches District, San Gabriel', 'geo' => 'Santa Lucia'],
            ['name' => 'Sta. Monica', 'alt' => 'Novaliches District', 'geo' => 'Santa Monica'],
        ],
        'District 6' => [
            ['name' => 'Apolonio Samson', 'alt' => 'Balintawak', 'geo' => null],
            ['name' => 'Baesa', 'alt' => 'Project 8', 'geo' => null],
            ['name' => 'Balong Bato', 'alt' => 'Balintawak', 'geo' => null],
            ['name' => 'Culiat', 'alt' => 'Tandang Sora', 'geo' => null],
            ['name' => 'New Era', 'alt' => 'Iglesia ni Cristo/Central', 'geo' => null],
            ['name' => 'Pasong Tamo', 'alt' => 'Tandang Sora', 'geo' => null],
            ['name' => 'Sangandaan', 'alt' => 'Project 8', 'geo' => null],
            ['name' => 'Sauyo', 'alt' => 'Novaliches District', 'geo' => null],
            ['name' => 'Talipapa', 'alt' => 'Novaliches District', 'geo' => null],
            ['name' => 'Tandang Sora', 'alt' => 'Banlat', 'geo' => null],
            ['name' => 'Unang Sigaw', 'alt' => 'Balintawak', 'geo' => null],
        ],
    ];

    return $districts;
}

/**
 * True when $barangay is a valid barangay of $district (exact canonical names).
 */
function qcIsValidLocation(string $district, string $barangay): bool
{
    $districts = qcDistricts();
    if (!isset($districts[$district])) {
        return false;
    }
    foreach ($districts[$district] as $entry) {
        if ($entry['name'] === $barangay) {
            return true;
        }
    }
    return false;
}

// Testing address data for Central Visayas (Region VII).
// Reflects EO 64 (2024) re-establishment of Negros Island Region:
//   Region VII → Cebu, Bohol
//   NIR       → Negros Occidental, Negros Oriental, Siquijor
//
// Used by database/seeders/TestingSeeder.php to generate
// geographically distributed test clients.
//
// Slot counts determine client distribution proportion:
//   Cebu   8 / 13 ≈ 62 %
//   Bohol  5 / 13 ≈ 38 %

export interface TestingProvinceSpec {
  province: string;
  cities: string[];
  slots: number;
}

export const regionVIIProvinces: TestingProvinceSpec[] = [
  {
    province: 'Cebu',
    cities: [
      'Cebu City', 'Lapu-Lapu City', 'Mandaue City', 'Talisay City',
      'Toledo City', 'Danao City', 'Carcar City', 'Naga City',
      'Bogo City', 'Minglanilla',
    ],
    slots: 8,
  },
  {
    province: 'Bohol',
    cities: [
      'Tagbilaran City', 'Panglao', 'Dauis', 'Tubigon', 'Talibon',
      'Ubay', 'Carmen', 'Jagna', 'Loon', 'Anda',
    ],
    slots: 5,
  },
];

export const negrosIslandProvinces: TestingProvinceSpec[] = [
  {
    province: 'Negros Occidental',
    cities: [
      'Bacolod City', 'Bago City', 'Cadiz City', 'Escalante City',
      'Himamaylan City', 'Kabankalan City', 'La Carlota City',
      'Sagay City', 'San Carlos City', 'Silay City', 'Sipalay City',
      'Talisay City', 'Victorias City',
    ],
    slots: 5,
  },
  {
    province: 'Negros Oriental',
    cities: [
      'Dumaguete City', 'Bais City', 'Bayawan City', 'Tanjay City',
      'Guihulngan City', 'Sibulan', 'Valencia', 'Bacong', 'Amlan', 'Mabinay',
    ],
    slots: 5,
  },
  {
    province: 'Siquijor',
    cities: [
      'Siquijor', 'Larena', 'Lazi', 'Maria', 'San Juan', 'Enrique Villanueva',
    ],
    slots: 2,
  },
];

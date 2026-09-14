<?php

namespace Database\Seeders\Staging;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Deterministic random-data engine + content pools for StagingSeeder.
 *
 * All randomness flows through a single mt_rand() stream fixed with
 * mt_srand(self::SEED), so identical call order yields identical output on
 * every run. Never call raw rand()/array_rand()/random_int() in seeder code —
 * route every draw through this class.
 *
 * UUID values themselves come from Str::uuid() (random_bytes, not seedable),
 * but their *assignment order* is deterministic, which is sufficient for
 * reproducible relationships and audit digests (see plan §6).
 *
 * PII note: values produced here are PLAIN. The seeding phase (T5+) is
 * responsible for writing PII through the models' EncryptedString casts.
 *
 * Content pools are copied verbatim from TestingSeeder (which stays
 * untouched); the address pool is extended with Negros Oriental + Siquijor
 * per the staging plan.
 */
class StagingDataFactory
{
    /**
     * Fixed RNG seed — every StagingSeeder run starts from this state.
     */
    public const SEED = 20260909;

    /**
     * Tracker alphabet: Crockford base32 (excludes I, L, O, U), mirroring
     * App\Services\CaseNumberGenerator::TRACKER_ALPHABET.
     */
    public const TRACKER_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const TRACKER_TOKEN_LENGTH = 10;

    /**
     * Case-number width, mirroring CaseNumberGenerator::CASE_NUMBER_PAD.
     * str_pad() never truncates, so sequences past 99999 widen naturally.
     */
    public const CASE_NUMBER_PAD = 5;

    /**
     * Probability a client record carries a middle name / name suffix.
     */
    public const MIDDLE_NAME_PROBABILITY = 0.60;

    public const SUFFIX_PROBABILITY = 0.03;

    /**
     * Share of OFW vs next-of-kin client types. Plain-string values mirror
     * App\Models\CaseFile::CLIENT_TYPE_OFW / CLIENT_TYPE_NEXT_OF_KIN without
     * importing the app layer into this foundation class.
     */
    public const OFW_SHARE = 0.60;

    /**
     * Filipino surname pool (verbatim from TestingSeeder).
     *
     * @var list<string>
     */
    private const SURNAMES = [
        'Dela Cruz', 'Santos', 'Reyes', 'Ramos', 'Mendoza', 'Garcia',
        'Martinez', 'Flores', 'Cruz', 'Aquino', 'Castro', 'Bautista',
        'Villanueva', 'Fernandez', 'Lopez', 'Gonzales', 'Rodriguez',
        'Rivera', 'Jimenez', 'Tan', 'Mercado', 'Gonzaga', 'Salvador',
        'Pascual', 'De Leon', 'Soriano', 'Navarro', 'Acosta',
        'Delos Santos', 'Manalo', 'Santiago', 'Ferrer', 'Salazar',
        'Gutierrez', 'Buenaventura', 'Tolentino', 'Panganiban',
        'Sarmiento', 'Gatdula', 'Dimaculangan', 'Kalaw', 'Sangalang',
        'Lacsamana',
    ];

    /**
     * @var list<string>
     */
    private const FEMALE_NAMES = [
        'Maria', 'Ana', 'Lucia', 'Cristina', 'Rosario', 'Teresa',
        'Josefina', 'Dolores', 'Guadalupe', 'Concepcion', 'Caridad',
        'Lourdes', 'Milagros', 'Erlinda', 'Corazon', 'Luzviminda',
        'Perla', 'Bella', 'Fe', 'Grace', 'Hope', 'Joy', 'Rose',
        'Pearl', 'Gemma', 'Donna', 'Regine',
    ];

    /**
     * @var list<string>
     */
    private const MALE_NAMES = [
        'Juan', 'Jose', 'Carlos', 'Antonio', 'Manuel', 'Francisco',
        'Pedro', 'Miguel', 'Ramon', 'Benito', 'Eduardo', 'Ernesto',
        'Rodrigo', 'Gregorio', 'Vicente', 'Fernando', 'Roberto',
        'Mario', 'Danilo', 'Ricardo', 'Alberto', 'Jaime', 'Andres',
        'Felipe', 'Gerardo', 'Arturo', 'Efren', 'Nestor', 'Rolando',
    ];

    /**
     * @var list<string>
     */
    private const MIDDLE_INITIALS = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'L',
        'M', 'N', 'P', 'R', 'S', 'T', 'V',
    ];

    /**
     * @var list<string>
     */
    private const SUFFIXES = ['Jr.', 'Sr.', 'III'];

    /**
     * Address pool. Cebu + Bohol are verbatim from TestingSeeder; Negros
     * Oriental + Siquijor extend the pool for the full Region VII footprint
     * per the staging plan. Slots weight each province's share of the
     * location cycle.
     *
     * @var list<array{province: string, cities: list<string>, slots: int}>
     */
    private const PROVINCES = [
        [
            'province' => 'Cebu',
            'cities' => [
                'Cebu City', 'Lapu-Lapu City', 'Mandaue City', 'Talisay City',
                'Toledo City', 'Danao City', 'Carcar City', 'Naga City',
                'Bogo City', 'Minglanilla',
            ],
            'slots' => 8,
        ],
        [
            'province' => 'Bohol',
            'cities' => [
                'Tagbilaran City', 'Panglao', 'Dauis', 'Tubigon', 'Talibon',
                'Ubay', 'Carmen', 'Jagna', 'Loon', 'Anda',
            ],
            'slots' => 5,
        ],
        [
            'province' => 'Negros Oriental',
            'cities' => [
                'Dumaguete City', 'Bais City', 'Bayawan City', 'Canlaon City',
                'Guihulngan City', 'Tanjay City', 'Sibulan', 'Valencia',
                'Bacong', 'Dauin',
            ],
            'slots' => 4,
        ],
        [
            'province' => 'Siquijor',
            'cities' => [
                'Siquijor', 'Larena', 'Enrique Villanueva',
                'Maria', 'Lazi', 'San Juan',
            ],
            'slots' => 1,
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const REGION_BY_PROVINCE = [
        'Cebu' => 'Central Visayas',
        'Bohol' => 'Central Visayas',
        'Negros Oriental' => 'Negros Island Region',
        'Siquijor' => 'Negros Island Region',
    ];

    /**
     * @var list<string>
     */
    private const BARANGAYS = [
        'Poblacion', 'Mabini', 'Rizal', 'Lahug', 'Banilad', 'Guadalupe',
        'Basak', 'Tisa', 'Punta Princesa', 'Mambaling', 'Bulacao',
        'Inayawan', 'Labangon', 'Sambag 1', 'Sambag 2', 'Kamputhaw',
        'Kasambagan', 'Ermita', 'Capitol Site', 'Suba', 'San Nicolas',
        'Sawang Calero',
    ];

    /**
     * @var list<string>
     */
    private const COUNTRIES = [
        'Saudi Arabia', 'UAE', 'Hong Kong', 'Taiwan', 'Singapore',
        'Qatar', 'Kuwait', 'Japan', 'Malaysia', 'South Korea',
    ];

    /**
     * @var list<string>
     */
    private const EMPLOYERS = [
        'Saudi Construction Co.', 'Dubai Hospitality Group',
        'HK Domestic Agency', 'Taiwan Electronics Inc.',
        'Singapore Marine Services', 'Qatar Petroleum Services',
        'Kuwait Medical Center', 'Tokyo Manufacturing Co.',
        'Kuala Lumpur Services', 'Seoul Tech Corp',
    ];

    /**
     * @var list<string>
     */
    private const POSITIONS = [
        'Domestic Worker', 'Heavy Equipment Operator',
        'Production Supervisor', 'Nurse', 'Engineer', 'Driver',
        'Clerk', 'Sales Assistant', 'Technician', 'Laborer',
    ];

    /**
     * @var list<string|null>
     */
    private const VULNERABILITIES = [
        'PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person',
        'None', null,
    ];

    /**
     * @var list<string>
     */
    private const RELATIONSHIPS = ['Spouse', 'Parent', 'Sibling', 'Child'];

    /**
     * @var list<string>
     */
    private const FEEDBACK_COMMENTS = [
        'Maayos ang serbisyo ng ahensya. Salamat po!',
        'Mabilis ang processing ng mga documents.',
        'Magalang at matulungin ang mga staff.',
        'Sana po mapabilis pa ang proseso.',
        'Satisfied naman ako sa assistance na natanggap.',
        'Mabagal minsan ang response pero okay naman ang resulta.',
        'Malaking tulong ito sa mga OFW na katulad ko.',
        'Maayos ang pag-handle ng aking kaso.',
        'Salamat sa mabilis na action sa aking reklamo.',
        'Maganda ang serbisyo, maraming salamat!',
        'Excellent service from the agency staff.',
        'Very helpful and responsive to my concerns.',
    ];

    /**
     * Weighted location cycle built from PROVINCES slots.
     *
     * @var list<array{province: string, cities: list<string>, slots: int}>
     */
    private array $locationCycle = [];

    private int $locationIndex = 0;

    public function __construct()
    {
        $this->resetSeed();
        $this->buildLocationCycle();
    }

    // ------------------------------------------------------------------
    // RNG core — every random draw in seeder code goes through these.
    // ------------------------------------------------------------------

    /**
     * (Re)seed the deterministic stream. Defaults to the fixed plan seed.
     */
    public function resetSeed(?int $seed = null): void
    {
        mt_srand($seed ?? self::SEED);
    }

    /**
     * Deterministic integer in [$min, $max].
     */
    public function int(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /**
     * Deterministic float in [$min, $max] rounded to $precision decimals.
     */
    public function float(float $min, float $max, int $precision = 2): float
    {
        $scale = 10 ** $precision;

        return mt_rand((int) round($min * $scale), (int) round($max * $scale)) / $scale;
    }

    /**
     * Deterministic coin flip; true with the given probability (0.0–1.0).
     */
    public function boolean(float $probability = 0.5): bool
    {
        return (mt_rand() / mt_getrandmax()) < $probability;
    }

    /**
     * Uniform pick from a non-empty pool.
     *
     * @template T
     *
     * @param  list<T>  $pool
     * @return T
     */
    public function pick(array $pool): mixed
    {
        if ($pool === []) {
            throw new \InvalidArgumentException('Cannot pick from an empty pool.');
        }

        return $pool[mt_rand(0, count($pool) - 1)];
    }

    /**
     * Weighted pick. Keys are the values to choose from, values are
     * non-negative integer weights.
     *
     * @param  array<int|string, int>  $weights
     */
    public function weightedPick(array $weights): int|string
    {
        $total = array_sum($weights);

        if ($total <= 0) {
            throw new \InvalidArgumentException('Weighted pick requires a positive total weight.');
        }

        $roll = mt_rand(1, $total);

        foreach ($weights as $value => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $value;
            }
        }

        return array_key_last($weights);
    }

    /**
     * Fisher-Yates shuffle driven by the deterministic stream. (PHP's native
     * shuffle() is deliberately avoided so the algorithm stays explicit and
     * version-independent.)
     *
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    /**
     * Next UUID in deterministic assignment order. The UUID bytes themselves
     * are random, but the order in which rows receive them is fixed — see
     * the class docblock.
     */
    public function uuid(): string
    {
        return (string) Str::uuid();
    }

    // ------------------------------------------------------------------
    // People
    // ------------------------------------------------------------------

    /**
     * @return 'MALE'|'FEMALE'
     */
    public function sex(): string
    {
        return $this->boolean() ? 'MALE' : 'FEMALE';
    }

    /**
     * @param  'MALE'|'FEMALE'  $sex
     */
    public function firstName(string $sex): string
    {
        return $this->pick($sex === 'MALE' ? self::MALE_NAMES : self::FEMALE_NAMES);
    }

    public function surname(): string
    {
        return $this->pick(self::SURNAMES);
    }

    /**
     * Middle initial, present ~60% of the time (TestingSeeder parity).
     */
    public function middleName(): ?string
    {
        return $this->boolean(self::MIDDLE_NAME_PROBABILITY) ? $this->pick(self::MIDDLE_INITIALS) : null;
    }

    /**
     * Name suffix, present ~3% of the time (TestingSeeder parity).
     */
    public function suffix(): ?string
    {
        return $this->boolean(self::SUFFIX_PROBABILITY) ? $this->pick(self::SUFFIXES) : null;
    }

    public function contactNumber(): string
    {
        return '09'.str_pad((string) $this->int(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    public function emailFor(string $firstName, string $lastName): string
    {
        return strtolower($firstName).'.'.strtolower(str_replace(' ', '', $lastName))
            .$this->int(1, 9999).'@email.com';
    }

    /**
     * Date of birth (Y-m-d) for someone aged [$minAge, $maxAge] at $asOf.
     */
    public function dateOfBirth(Carbon $asOf, int $minAge = 21, int $maxAge = 65): string
    {
        $year = (int) $asOf->format('Y') - $this->int($minAge, $maxAge);

        return sprintf('%d-%02d-%02d', $year, $this->int(1, 12), $this->int(1, 28));
    }

    // ------------------------------------------------------------------
    // Addresses
    // ------------------------------------------------------------------

    /**
     * Next province/city from the weighted location cycle, advancing the
     * cycle pointer. Deterministic for a fixed call order; call
     * resetLocationCycle() to restart the cycle (e.g. in tests).
     *
     * @return array{province: string, city: string}
     */
    public function location(): array
    {
        $spec = $this->locationCycle[$this->locationIndex % count($this->locationCycle)];
        $this->locationIndex++;

        return [
            'province' => $spec['province'],
            'city' => $this->pick($spec['cities']),
        ];
    }

    public function resetLocationCycle(): void
    {
        $this->locationIndex = 0;
    }

    public function regionForProvince(string $province): string
    {
        return self::REGION_BY_PROVINCE[$province] ?? 'Central Visayas';
    }

    public function barangay(): string
    {
        return $this->pick(self::BARANGAYS);
    }

    public function streetAddress(): string
    {
        return $this->int(1, 999).' '.$this->barangay().' St';
    }

    // ------------------------------------------------------------------
    // Employment / vulnerability / relationships
    // ------------------------------------------------------------------

    public function country(): string
    {
        return $this->pick(self::COUNTRIES);
    }

    public function employer(): string
    {
        return $this->pick(self::EMPLOYERS);
    }

    public function position(): string
    {
        return $this->pick(self::POSITIONS);
    }

    public function vulnerability(): ?string
    {
        return $this->pick(self::VULNERABILITIES);
    }

    public function relationship(): string
    {
        return $this->pick(self::RELATIONSHIPS);
    }

    public function feedbackComment(): string
    {
        return $this->pick(self::FEEDBACK_COMMENTS);
    }

    /**
     * 'OFW' (~60%) or 'NEXT_OF_KIN'. Values mirror the CaseFile constants.
     */
    public function clientType(): string
    {
        return $this->boolean(self::OFW_SHARE) ? 'OFW' : 'NEXT_OF_KIN';
    }

    // ------------------------------------------------------------------
    // Case identifiers (must match App\Services\CaseNumberGenerator)
    // ------------------------------------------------------------------

    /**
     * OWB-{YYYYMM}-{NNNNN} for the given period and per-month sequence.
     */
    public function caseNumber(int $period, int $sequence): string
    {
        return sprintf('OWB-%d-%s', $period, str_pad((string) $sequence, self::CASE_NUMBER_PAD, '0', STR_PAD_LEFT));
    }

    /**
     * OWBAP- + 10 Crockford base32 characters. Drawn from the deterministic
     * stream (production uses random_int; the seeder must be reproducible,
     * so mt_rand is used deliberately). Uniqueness bookkeeping stays with
     * the caller (T5), as in TestingSeeder's $usedTrackers set.
     */
    public function trackerNumber(): string
    {
        $max = strlen(self::TRACKER_ALPHABET) - 1;
        $token = '';

        for ($i = 0; $i < self::TRACKER_TOKEN_LENGTH; $i++) {
            $token .= self::TRACKER_ALPHABET[$this->int(0, $max)];
        }

        return 'OWBAP-'.$token;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function buildLocationCycle(): void
    {
        $this->locationCycle = [];

        foreach (self::PROVINCES as $spec) {
            for ($slot = 0; $slot < $spec['slots']; $slot++) {
                $this->locationCycle[] = $spec;
            }
        }
    }
}

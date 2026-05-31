export interface PhilippineRegion {
  code: string;
  name: string;
}

export interface PhilippineProvince {
  code: string;
  name: string;
  regionCode: string;
}

export interface PhilippineCity {
  code: string;
  name: string;
  type: 'City' | 'Municipality';
  provinceCode: string;
}

export interface PhilippineBarangay {
  code: string;
  name: string;
  cityCode: string;
}

export interface CountryOption {
  code: string;
  name: string;
}

export interface AddressValues {
  region: string;
  province: string;
  city_municipality: string;
  barangay: string;
  street: string;
}

export interface ClientSummary {
  id: string;
  first_name: string;
  last_name: string;
  middle_name: string | null;
  suffix: string | null;
  sex: string | null;
  date_of_birth: string | null;
  avatar_url: string | null;
  case_file: {
    case_number: string;
  } | null;
}

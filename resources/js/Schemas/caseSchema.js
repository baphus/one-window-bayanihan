import { z } from 'zod';

const nokAddressSchema = z.object({
  region: z.string().optional(),
  province: z.string().optional(),
  city_municipality: z.string().optional(),
  barangay: z.string().optional(),
  street: z.string().optional(),
  zip_code: z.string().optional(),
});

const nextOfKinSchema = z.object({
  full_name: z.string().min(1, 'Full name is required.'),
  relationship: z.string().min(1, 'Relationship is required.'),
  contact_number: z.string().min(1, 'Contact number is required.'),
  email: z
    .union([z.string().email('Please provide a valid email address.'), z.literal('')])
    .optional(),
  address: nokAddressSchema.optional(),
});

export const createCaseSchema = z.object({
  client: z.object({
    first_name: z.string().min(1, 'First name is required.'),
    last_name: z.string().min(1, 'Last name is required.'),
    middle_name: z.string().optional(),
    suffix: z.string().optional(),
    date_of_birth: z.string().min(1, 'Date of birth is required.'),
    sex: z.string().min(1, 'Sex is required.'),
    email: z
      .union([z.string().email('Please provide a valid email address.'), z.literal('')])
      .optional(),
    contact_number: z.string().min(1, 'Contact number is required.'),
  }),
  address: z.object({
    region: z.string().min(1, 'Region is required.'),
    province: z.string().min(1, 'Province is required.'),
    city_municipality: z.string().min(1, 'City/Municipality is required.'),
    barangay: z.string().min(1, 'Barangay is required.'),
    street: z.string().optional(),
    zip_code: z.string().optional(),
  }),
  employment: z.object({
    employer_name: z.string().optional(),
    employer_address: z.string().optional(),
    employer_contact: z.string().optional(),
    position: z.string().optional(),
    income: z.string().optional(),
  }),
  next_of_kin: z.array(nextOfKinSchema),
  type: z.string().min(1, 'Case type is required.'),
  description: z.string().optional(),
  remarks: z.string().optional(),
});


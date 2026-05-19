import { z } from 'zod';

export const UserSchema = z.object({
  id: z.number(),
  name: z.string(),
  email: z.string().email(),
  locale: z.enum(['pt_BR', 'en', 'es']).nullable(),
  is_admin: z.boolean().optional().default(false),
});
export type User = z.infer<typeof UserSchema>;

export const AdminMetricsSchema = z.object({
  totals: z.object({
    users: z.number().int(),
    applications: z.number().int(),
    resumes: z.number().int(),
    active_users: z.number().int(),
  }),
  top_applicants: z.array(z.object({
    user_id: z.number().int(),
    name: z.string(),
    email: z.string(),
    applications_count: z.number().int(),
  })),
  by_location: z.object({
    countries: z.array(z.object({
      country_code: z.string(),
      count: z.number().int(),
    })),
    states: z.array(z.object({
      country_code: z.string(),
      state_code: z.string().nullable(),
      state_name: z.string().nullable(),
      count: z.number().int(),
    })),
    cities: z.array(z.object({
      country_code: z.string(),
      city: z.string(),
      count: z.number().int(),
    })),
    without_location: z.number().int(),
  }),
  generated_at: z.string(),
});
export type AdminMetrics = z.infer<typeof AdminMetricsSchema>;

export const SupportedCountrySchema = z.object({
  code: z.string().length(2),
  name_pt: z.string(),
  name_en: z.string(),
  name_es: z.string(),
  postal_pattern: z.string(),
  postal_example: z.string(),
  supports_lookup: z.boolean(),
});
export type SupportedCountry = z.infer<typeof SupportedCountrySchema>;

export const PostalCodeLookupResultSchema = z.object({
  country_code: z.string(),
  postal_code: z.string(),
  state_code: z.string().nullable(),
  state_name: z.string().nullable(),
  city: z.string().nullable(),
  source: z.string(),
});
export type PostalCodeLookupResult = z.infer<typeof PostalCodeLookupResultSchema>;

export const ErrorLogLevelSchema = z.enum(['error', 'warning']);
export type ErrorLogLevel = z.infer<typeof ErrorLogLevelSchema>;

export const ErrorLogSchema = z.object({
  id: z.number().int(),
  level: ErrorLogLevelSchema,
  exception_class: z.string(),
  message: z.string(),
  file: z.string().nullable(),
  line: z.number().int().nullable(),
  url: z.string().nullable(),
  method: z.string().nullable(),
  stack_trace: z.string().nullable(),
  user: z.object({
    id: z.number().int(),
    name: z.string(),
    email: z.string(),
  }).nullable(),
  occurred_at: z.string(),
});
export type ErrorLog = z.infer<typeof ErrorLogSchema>;

export const AdminErrorLogsResponseSchema = z.object({
  data: z.array(ErrorLogSchema),
  total: z.number().int(),
  generated_at: z.string(),
});
export type AdminErrorLogsResponse = z.infer<typeof AdminErrorLogsResponseSchema>;

export const ApplicationStatusSchema = z.enum([
  'applied',
  'screening',
  'assessment',
  'interview_hr',
  'interview_tech',
  'offer',
  'accepted',
  'rejected',
  'withdrawn',
]);
export type ApplicationStatus = z.infer<typeof ApplicationStatusSchema>;

export const SeniorityEnum = z.enum(['intern', 'junior', 'mid', 'senior', 'staff', 'principal']);
export type Seniority = z.infer<typeof SeniorityEnum>;

export const ModalityEnum = z.enum(['remote', 'hybrid', 'onsite']);
export type Modality = z.infer<typeof ModalityEnum>;

export const LocaleEnum = z.enum(['pt_BR', 'en', 'es']);
export type Locale = z.infer<typeof LocaleEnum>;

export const SkillSchema = z.object({
  id: z.number(),
  name: z.string(),
  category: z.string().nullable(),
});
export type Skill = z.infer<typeof SkillSchema>;

export const ProfileSchema = z.object({
  id: z.number(),
  user_id: z.number(),
  desired_role: z.string().nullable(),
  seniority: SeniorityEnum.nullable(),
  modality: ModalityEnum.nullable(),
  salary_min: z.number().nullable(),
  salary_max: z.number().nullable(),
  salary_currency: z.string().nullable(),
  country_code: z.string().nullable(),
  postal_code: z.string().nullable(),
  state_code: z.string().nullable(),
  state_name: z.string().nullable(),
  city: z.string().nullable(),
  languages: z.array(LocaleEnum).nullable(),
  bio: z.string().nullable(),
  skills: z.array(SkillSchema).default([]),
});
export type Profile = z.infer<typeof ProfileSchema>;

export const JobSourceSchema = z.object({
  id: z.number(),
  source: z.string(),
  external_url: z.string().url().nullable(),
});
export type JobSource = z.infer<typeof JobSourceSchema>;

export const JobSchema = z.object({
  id: z.number(),
  title: z.string(),
  description_html: z.string().nullable().optional().default(null),
  location: z.string().nullable(),
  modality: z.enum(['remote', 'hybrid', 'onsite']).nullable(),
  seniority: z.enum(['intern', 'junior', 'mid', 'senior', 'staff', 'principal']).nullable(),
  stack: z.array(z.string()),
  salary_min: z.number().nullable(),
  salary_max: z.number().nullable(),
  salary_currency: z.string().nullable(),
  posted_at: z.string().nullable(),
  match_score: z.number().optional(),
  matched_stack: z.array(z.string()).optional(),
  has_applied: z.boolean().optional().default(false),
  language: LocaleEnum.nullable().optional(),
  contact_email: z.string().nullable().optional().default(null),
  company: z
    .object({
      id: z.number(),
      name: z.string(),
      logo_url: z.string().nullable(),
    })
    .nullable(),
  sources: z.array(JobSourceSchema).optional().default([]),
});
export type Job = z.infer<typeof JobSchema>;

export const JobsPageSchema = z.object({
  data: z.array(JobSchema),
  current_page: z.number(),
  last_page: z.number(),
  total: z.number(),
});
export type JobsPage = z.infer<typeof JobsPageSchema>;

export const ApplicationEventSchema = z.object({
  id: z.number(),
  event_type: z.string(),
  payload: z.record(z.unknown()).nullable(),
  occurred_at: z.string(),
});
export type ApplicationEvent = z.infer<typeof ApplicationEventSchema>;

export const ResumeSectionTypeEnum = z.enum([
  'summary',
  'experience',
  'education',
  'skill',
  'language',
  'project',
  'contact',
]);
export type ResumeSectionType = z.infer<typeof ResumeSectionTypeEnum>;

export const ResumeSectionSchema = z.object({
  id: z.number().optional(),
  type: ResumeSectionTypeEnum,
  order: z.number().int().min(0),
  content: z.record(z.unknown()),
});
export type ResumeSection = z.infer<typeof ResumeSectionSchema>;

export const ResumeSchema = z.object({
  id: z.number(),
  user_id: z.number(),
  title: z.string(),
  language: LocaleEnum,
  is_pdf_upload: z.boolean(),
  file_path: z.string().nullable(),
  metadata: z.record(z.unknown()).nullable().default({}),
  sections: z.array(ResumeSectionSchema).default([]),
  sections_count: z.number().optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
});
export type Resume = z.infer<typeof ResumeSchema>;

export const ResumesPageSchema = z.object({
  data: z.array(ResumeSchema),
  current_page: z.number(),
  last_page: z.number(),
  total: z.number(),
});
export type ResumesPage = z.infer<typeof ResumesPageSchema>;

export const MetricsKpisSchema = z.object({
  total_applications: z.number().int(),
  total_responses: z.number().int(),
  total_interviews: z.number().int(),
  total_offers: z.number().int(),
  total_rejections: z.number().int(),
  response_rate: z.number(),
  interview_rate: z.number(),
  offer_rate: z.number(),
});
export type MetricsKpis = z.infer<typeof MetricsKpisSchema>;

export const MetricsChannelSchema = z.object({
  source: z.string(),
  applications: z.number().int(),
  responses: z.number().int(),
  response_rate: z.number(),
});
export type MetricsChannel = z.infer<typeof MetricsChannelSchema>;

export const MetricsFunnelStageSchema = z.object({
  status: z.string(),
  label: z.string(),
  reached: z.number().int(),
});
export type MetricsFunnelStage = z.infer<typeof MetricsFunnelStageSchema>;

export const MetricsHeatmapCellSchema = z.object({
  weekday: z.number().int().min(0).max(6),
  hour: z.number().int().min(0).max(23),
  count: z.number().int().min(0),
});
export type MetricsHeatmapCell = z.infer<typeof MetricsHeatmapCellSchema>;

export const MetricsInsightSchema = z.object({
  key: z.string(),
  severity: z.enum(['info', 'warning', 'success']),
  message: z.string(),
});
export type MetricsInsight = z.infer<typeof MetricsInsightSchema>;

export const MetricsMonthlyDaySchema = z.object({
  day: z.number().int().min(1).max(31),
  count: z.number().int().min(0),
});
export type MetricsMonthlyDay = z.infer<typeof MetricsMonthlyDaySchema>;

export const MetricsMonthlySchema = z.object({
  year: z.number().int(),
  month: z.number().int().min(1).max(12),
  days_in_month: z.number().int().min(28).max(31),
  days: z.array(MetricsMonthlyDaySchema),
  total_applications: z.number().int().min(0),
  total_responses: z.number().int().min(0),
  response_rate: z.number(),
});
export type MetricsMonthly = z.infer<typeof MetricsMonthlySchema>;

export const MetricsSummarySchema = z.object({
  kpis: MetricsKpisSchema,
  channels: z.array(MetricsChannelSchema),
  funnel: z.array(MetricsFunnelStageSchema),
  heatmap: z.array(MetricsHeatmapCellSchema),
  avgDaysBetweenStages: z.number().nullable(),
  insights: z.array(MetricsInsightSchema),
  rangeFrom: z.string(),
  rangeTo: z.string(),
  monthly: MetricsMonthlySchema,
});
export type MetricsSummary = z.infer<typeof MetricsSummarySchema>;

export const ApplicationSchema = z.object({
  id: z.number(),
  status: ApplicationStatusSchema,
  applied_at: z.string(),
  archived_at: z.string().nullable().optional(),
  notes: z.string().nullable(),
  expected_salary: z.number().nullable(),
  source: z.string().nullable(),
  manual_title: z.string().nullable().optional(),
  manual_company: z.string().nullable().optional(),
  job_url: z.string().nullable().optional(),
  resume_id: z.number().nullable().optional(),
  resume: ResumeSchema.nullable().optional(),
  job: JobSchema.nullable().optional(),
  events: z.array(ApplicationEventSchema).optional().default([]),
});
export type Application = z.infer<typeof ApplicationSchema>;

export const SuggestionAuthorSchema = z.object({
  id: z.number(),
  name: z.string(),
});
export type SuggestionAuthor = z.infer<typeof SuggestionAuthorSchema>;

export const SuggestionVoteValueSchema = z.union([z.literal(1), z.literal(-1)]);
export type SuggestionVoteValue = z.infer<typeof SuggestionVoteValueSchema>;

export const SuggestionRankSchema = z.union([z.literal(1), z.literal(2), z.literal(3)]);
export type SuggestionRank = z.infer<typeof SuggestionRankSchema>;

export const SuggestionSchema = z.object({
  id: z.number().int(),
  user: SuggestionAuthorSchema,
  title: z.string(),
  body: z.string(),
  upvotes_count: z.number().int(),
  downvotes_count: z.number().int(),
  score: z.number().int(),
  my_vote: SuggestionVoteValueSchema.nullable(),
  rank: SuggestionRankSchema.nullable(),
  created_at: z.string(),
});
export type Suggestion = z.infer<typeof SuggestionSchema>;

export const SuggestionsPageSchema = z.object({
  data: z.array(SuggestionSchema),
  current_page: z.number(),
  last_page: z.number(),
  total: z.number(),
});
export type SuggestionsPage = z.infer<typeof SuggestionsPageSchema>;

export const SuggestionQuotaSchema = z.object({
  used: z.number().int(),
  limit: z.number().int(),
  next_slot_at: z.string().nullable(),
});
export type SuggestionQuota = z.infer<typeof SuggestionQuotaSchema>;

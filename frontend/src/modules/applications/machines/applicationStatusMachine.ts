import { setup } from 'xstate';
import type { ApplicationStatus } from '@/shared/api/schemas';

/**
 * State machine para o fluxo de status de uma candidatura.
 * Espelha as transições definidas no backend (ApplicationStatus::allowedTransitions).
 */
export const applicationStatusMachine = setup({
  types: {
    events: {} as { type: 'ADVANCE'; to: ApplicationStatus } | { type: 'REJECT' } | { type: 'WITHDRAW' },
  },
}).createMachine({
  id: 'applicationStatus',
  initial: 'applied',
  states: {
    applied: {
      on: { ADVANCE: 'screening', REJECT: 'rejected', WITHDRAW: 'withdrawn' },
    },
    screening: {
      on: { ADVANCE: 'assessment', REJECT: 'rejected', WITHDRAW: 'withdrawn' },
    },
    assessment: {
      on: { ADVANCE: 'interview_hr', REJECT: 'rejected', WITHDRAW: 'withdrawn' },
    },
    interview_hr: {
      on: { ADVANCE: 'interview_tech', REJECT: 'rejected', WITHDRAW: 'withdrawn' },
    },
    interview_tech: {
      on: { ADVANCE: 'offer', REJECT: 'rejected', WITHDRAW: 'withdrawn' },
    },
    offer: {
      on: { ADVANCE: 'accepted', REJECT: 'rejected', WITHDRAW: 'withdrawn' },
    },
    accepted: { type: 'final' },
    rejected: { type: 'final' },
    withdrawn: { type: 'final' },
  },
});

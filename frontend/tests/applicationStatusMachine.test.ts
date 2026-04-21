import { describe, it, expect } from 'vitest';
import { createActor } from 'xstate';
import { applicationStatusMachine } from '@/modules/applications/machines/applicationStatusMachine';

describe('applicationStatusMachine', () => {
  it('starts in applied', () => {
    const actor = createActor(applicationStatusMachine).start();
    expect(actor.getSnapshot().value).toBe('applied');
  });

  it('advances through the happy path to accepted', () => {
    const actor = createActor(applicationStatusMachine).start();
    actor.send({ type: 'ADVANCE', to: 'screening' });
    actor.send({ type: 'ADVANCE', to: 'assessment' });
    actor.send({ type: 'ADVANCE', to: 'interview_hr' });
    actor.send({ type: 'ADVANCE', to: 'interview_tech' });
    actor.send({ type: 'ADVANCE', to: 'offer' });
    actor.send({ type: 'ADVANCE', to: 'accepted' });

    expect(actor.getSnapshot().value).toBe('accepted');
    expect(actor.getSnapshot().status).toBe('done');
  });

  it('can reject at any non-final stage', () => {
    const actor = createActor(applicationStatusMachine).start();
    actor.send({ type: 'ADVANCE', to: 'screening' });
    actor.send({ type: 'REJECT' });

    expect(actor.getSnapshot().value).toBe('rejected');
  });

  it('ignores events after reaching final state', () => {
    const actor = createActor(applicationStatusMachine).start();
    actor.send({ type: 'REJECT' });
    actor.send({ type: 'ADVANCE', to: 'screening' });

    expect(actor.getSnapshot().value).toBe('rejected');
  });

  it('can withdraw at any non-final stage', () => {
    const actor = createActor(applicationStatusMachine).start();
    actor.send({ type: 'ADVANCE', to: 'screening' });
    actor.send({ type: 'ADVANCE', to: 'assessment' });
    actor.send({ type: 'WITHDRAW' });

    expect(actor.getSnapshot().value).toBe('withdrawn');
  });
});

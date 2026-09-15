import { describe, expect, it } from 'vitest';
import { organizationDisplayName } from '@/utils/organizationTitle';

describe('organizationDisplayName', () => {
  it('returns the organization name when it is present', () => {
    expect(organizationDisplayName({ name: 'Своя компания' })).toBe('Своя компания');
  });

  it('returns Новая компания when the name is missing', () => {
    expect(organizationDisplayName({ name: null })).toBe('Новая компания');
    expect(organizationDisplayName({ name: '   ' })).toBe('Новая компания');
  });
});

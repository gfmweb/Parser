export function organizationDisplayName(organization: { name: string | null }): string {
  const name = organization.name?.trim();

  return name !== undefined && name !== '' ? name : 'Новая компания';
}

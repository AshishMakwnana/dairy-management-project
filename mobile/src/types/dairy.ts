export type Dairy = {
  id: string;
  name: string;
  state: 'draft' | 'complete';
  location: {
    city: string | null;
    state: string | null;
    country: string;
  };
};

export type SetupChecklistItem = {
  id: 'dairy' | 'milk-quality' | 'pricing' | 'farmer' | 'staff';
  status: 'complete' | 'unverified' | 'optional-unverified';
};

export type DairyFormValues = {
  name: string;
  legalName: string;
  city: string;
  state: string;
  postalCode: string;
};

export type DairyCreatePayload = {
  name: string;
  legal_name?: string;
  location: {
    city: string;
    state: string;
    country: 'IN';
    postal_code?: string;
  };
  client_uuid: string;
};

export type DairyCreationDraft = {
  clientUuid: string;
  status: 'ready' | 'pending' | 'conflict';
  values: DairyFormValues;
};

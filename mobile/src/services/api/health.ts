import { environment } from '../../config/environment';

type HealthResponse = {
  success: boolean;
  data: {
    service: string;
    status: string;
    version: string;
  };
};

export async function checkApiHealth(): Promise<HealthResponse> {
  const response = await fetch(`${environment.apiBaseUrl}/health`);

  if (!response.ok) {
    throw new Error(`Health check failed with status ${response.status}.`);
  }

  return (await response.json()) as HealthResponse;
}

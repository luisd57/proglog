export interface ApiError {
  code: string;
  message: string;
  details?: Record<string, string>;
}

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: ApiError;
}

export interface ApiErrorBody {
  error?: ApiError;
}

// Reads the API error envelope out of an HttpErrorResponse-like object
// ({ error: { error: { message } } }), falling back to a generic message.
export function extractErrorMessage(err: unknown, fallback: string): string {
  if (err instanceof Object && 'error' in err) {
    const body: unknown = (err as { error: unknown }).error;
    if (body instanceof Object && 'error' in body) {
      const apiError: unknown = (body as { error: unknown }).error;
      if (apiError instanceof Object && 'message' in apiError) {
        return (apiError as { message: string }).message;
      }
    }
  }
  if (err instanceof Error) {
    return err.message;
  }
  return fallback;
}

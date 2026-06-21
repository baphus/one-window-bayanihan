import { useCallback, useState } from 'react';
import { z } from 'zod';

export default function useClientValidation(schema, data, setError) {
  const [isValid, setIsValid] = useState(true);

  const validate = useCallback(() => {
    const result = schema.safeParse(data);
    if (result.success) {
      setIsValid(true);
      return true;
    }

    setIsValid(false);
    for (const issue of result.error.issues) {
      const field = issue.path.join('.');
      setError(field, issue.message);
    }
    return false;
  }, [schema, data, setError]);

  return { validate, isValid };
}

import type { BoundFieldErrors } from '~/utils/error-message'

/**
 * Error display (CONVENTIONS §4.1, SCREENS S7): `errors.<code>` when the key exists, otherwise the
 * server `message`; `422` field errors are bound to form fields by path, and paths without a field
 * go to a form-level alert.
 *
 *   const { message, bind } = useErrorMessage()
 *   catch (error) {
 *     const bound = bind(error, ['email', 'organization.cr_number'])
 *     fieldErrors.value = bound.fields
 *     formError.value = bound.unmatched[0] ?? (isValidation(error) ? null : message(error))
 *   }
 */
export function useErrorMessage() {
  const { t, te } = useI18n()

  function message(error: unknown): string {
    return errorMessage(error, (key, params) => t(key, params ?? {}), key => te(key))
  }

  function bind(error: unknown, fields: readonly string[], aliases: Record<string, string> = {}): BoundFieldErrors {
    if (!(error instanceof ApiError) || !error.isValidation) return { fields: {}, unmatched: [] }
    return bindFieldErrors(error, fields, aliases)
  }

  return { message, bind }
}

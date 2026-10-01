/**
 * Private downloads (API.md §0.7, SCREENS §2.1): fetch the file as a blob with the bearer header,
 * then save it with the server's file name. Accepts a `File.download_path`
 * (`/api/app/v1/files/…/download`) or a path relative to the API base (`/billing/invoices/…/pdf`).
 * Errors (for example 409 `invoice_pdf_not_ready`) are thrown as `ApiError` with their code.
 */
export function useFileDownload() {
  const config = useRuntimeConfig()
  const downloading = ref(false)

  async function download(path: string, fallbackName?: string): Promise<void> {
    const url = resolveApiUrl(path, config.public.apiBase)
    if (!url) {
      throw new ApiError({ status: 400, code: 'bad_request', message: '', errors: {} })
    }
    downloading.value = true
    try {
      const response = await useApi().raw<Blob, 'blob'>(url, { responseType: 'blob' })
      const blob = response._data
      if (!blob) throw new ApiError({ status: response.status, code: 'server_error', message: '', errors: {} })
      const name = filenameFromDisposition(response.headers.get('content-disposition')) ?? sanitizeFilename(fallbackName ?? 'download')
      saveBlob(blob, name)
    }
    finally {
      downloading.value = false
    }
  }

  return { download, downloading: readonly(downloading) }
}

function saveBlob(blob: Blob, name: string): void {
  const href = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = href
  anchor.download = name
  anchor.rel = 'noopener'
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  setTimeout(() => URL.revokeObjectURL(href), 30_000)
}

import { useEffect, useState, useRef } from 'react'
import { router, usePage } from '@inertiajs/react'

export function useLazyProp(key, options = {}) {
  const { preserveScroll = true, preserveState = true } = options
  const { props } = usePage()
  const [data, setData] = useState(props[key] ?? null)
  const [isLoading, setIsLoading] = useState(props[key] === undefined || props[key] === null)
  const [error, setError] = useState(null)
  const fetchedRef = useRef(false)
  const mountedRef = useRef(true)

  useEffect(() => {
    mountedRef.current = true
    const currentValue = props[key]

    if ((currentValue === undefined || currentValue === null) && !fetchedRef.current) {
      fetchedRef.current = true
      setIsLoading(true)

      router.reload({
        only: [key],
        preserveScroll,
        preserveState,
        onSuccess: (page) => {
          if (mountedRef.current) {
            const newVal = page.props[key]
            setData(newVal ?? null)
            setIsLoading(false)
          }
        },
        onError: (errors) => {
          if (mountedRef.current) {
            setError(errors)
            setIsLoading(false)
          }
        },
      })
    } else if (currentValue !== undefined && currentValue !== null) {
      setData(currentValue)
      setIsLoading(false)
    }

    return () => {
      mountedRef.current = false
    }
  }, [key])

  return [data, isLoading, error]
}

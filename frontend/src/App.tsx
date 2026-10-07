import { useEffect, useState } from 'react'
import './App.css'

type ConnectionStatus = 'loading' | 'success' | 'error'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/+$/, '')

const messages = {
  loading: {
    title: 'Checking the connection',
    description: 'Connecting to Pet Care Tracker. This should only take a moment.',
    label: 'Checking',
  },
  success: {
    title: 'Everything is connected',
    description: 'The frontend received the expected response from the API.',
    label: 'Connected',
  },
  error: {
    title: 'We couldn’t reach the API',
    description: 'The connection failed or the response was unexpected. Try again in a moment.',
    label: 'Connection failed',
  },
}

function App() {
  const [status, setStatus] = useState<ConnectionStatus>('loading')
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    let cancelled = false
    const timeout = window.setTimeout(() => controller.abort(), 10_000)

    async function checkConnection() {
      try {
        const response = await fetch(`${apiBaseUrl}/health`, {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
          credentials: 'omit',
          signal: controller.signal,
        })
        if (!response.ok) throw new Error('Health request failed')

        const body: unknown = await response.json()
        if (typeof body !== 'object' || body === null || !('status' in body) || body.status !== 'ok') {
          throw new Error('Unexpected health response')
        }
        if (!cancelled) setStatus('success')
      } catch {
        if (!cancelled) setStatus('error')
      } finally {
        window.clearTimeout(timeout)
      }
    }

    void checkConnection()
    return () => {
      cancelled = true
      window.clearTimeout(timeout)
      controller.abort()
    }
  }, [attempt])

  const message = messages[status]

  function retry() {
    setStatus('loading')
    setAttempt((current) => current + 1)
  }

  return (
    <div className="app-shell">
      <header className="site-header">
        <a className="wordmark" href="/">Pet Care Tracker<span className="brand-dot" aria-hidden="true" /></a>
        <span className="environment-label">Development preview</span>
      </header>
      <main>
        <p className="eyebrow">A small beginning</p>
        <h1>A foundation for better care.</h1>
        <p className="intro">A place to keep your pet’s care and health history together. First, let’s make sure the connection works.</p>

        <section className={`connection-card connection-card--${status}`} aria-labelledby="connection-heading" aria-busy={status === 'loading'}>
          <div role="status" aria-live="polite" aria-atomic="true">
            <span className="status-badge"><span className="status-dot" aria-hidden="true" />{message.label}</span>
            <h2 id="connection-heading">{message.title}</h2>
            <p>{message.description}</p>
          </div>
          <button type="button" onClick={retry} disabled={status === 'loading'}>
            {status === 'loading' ? 'Checking…' : status === 'error' ? 'Try again' : 'Check again'}
            <span aria-hidden="true">↗</span>
          </button>
        </section>
        <p className="check-note">This checks API connectivity. It does not verify database readiness.</p>
      </main>
      <footer>Care starts with keeping track.</footer>
    </div>
  )
}

export default App

import { useState, useEffect } from 'react'
import { Link, useNavigate } from 'react-router-dom'

function SharedNavbar() {
  const [isMobileNavOpen, setIsMobileNavOpen] = useState(false)
  const [username, setUsername] = useState('Utente')
  const navigate = useNavigate()

  // Simula il recupero dell'utente loggato (in futuro da contesto/auth)
  useEffect(() => {
    // TODO: Recupera username da sessione o contesto autenticazione
    // Per ora usiamo un placeholder
    const storedUsername = localStorage.getItem('username') || 'Utente'
    setUsername(storedUsername)
  }, [])

  const toggleMobileNav = () => {
    setIsMobileNavOpen(!isMobileNavOpen)
  }

  const closeMobileNav = () => {
    setIsMobileNavOpen(false)
  }

  const handleLogout = async () => {
    try {
      // TODO: Implementare chiamata API per logout
      // Per ora simuli il logout e reindirizza
      localStorage.removeItem('username')
      localStorage.removeItem('token')
      navigate('/auth/register.php')
    } catch (error) {
      console.error('Errore durante il logout:', error)
    }
  }

  return (
    <>
      {/* Navbar Desktop */}
      <header className="dashboard-navbar" aria-label="Primary navigation">
        <button 
          type="button" 
          className="dashboard-navbar__toggle" 
          onClick={toggleMobileNav}
          aria-controls="mobile-nav-drawer" 
          aria-expanded={isMobileNavOpen}
          aria-label="Apri menu"
        >
          <span></span>
          <span></span>
          <span></span>
        </button>

        <div className="dashboard-navbar__brand" aria-label="StartupFlow home">
          <Link to="/" className="dashboard-navbar__logo">SF</Link>
        </div>

        <nav className="dashboard-navbar__links" aria-label="Static pages">
          <Link to="/#chi-siamo">Chi siamo</Link>
          <Link to="/#servizi">Servizi</Link>
          <Link to="/#contatti">Contatti</Link>
        </nav>

        <div className="dashboard-navbar__userarea">
          <button 
            type="button" 
            className="dashboard-navbar__user-trigger"
            aria-haspopup="dialog" 
            aria-expanded="false"
          >
            <div className="dashboard-navbar__user-avatar" aria-hidden="true">
              <span className="material-symbols-outlined">person</span>
            </div>
            <span className="dashboard-navbar__user">Ciao, {username}</span>
          </button>
          <button 
            type="button" 
            className="dashboard-navbar__logout-btn" 
            onClick={handleLogout}
            aria-label="Logout"
          >
            <span className="material-symbols-outlined" aria-hidden="true">logout</span>
          </button>
        </div>
      </header>

      {/* Mobile Navigation Drawer */}
      <div 
        className={`dashboard-navbar__backdrop ${isMobileNavOpen ? '' : 'hidden'}`}
        onClick={closeMobileNav}
        data-mobile-nav-backdrop
      ></div>

      <aside 
        className={`dashboard-navbar__drawer ${isMobileNavOpen ? '' : 'hidden'}`}
        id="mobile-nav-drawer" 
        aria-label="Menu mobile" 
        aria-hidden={!isMobileNavOpen}
      >
        <div className="dashboard-navbar__drawer-top">
          <span className="dashboard-navbar__logo">SF</span>
          <button 
            type="button" 
            className="dashboard-navbar__drawer-close" 
            onClick={closeMobileNav}
            aria-label="Chiudi menu"
          >
            <span className="material-symbols-outlined" aria-hidden="true">close</span>
          </button>
        </div>

        <div className="dashboard-navbar__drawer-content">
          <div className="dashboard-navbar__drawer-section">
            <nav className="dashboard-navbar__drawer-links" aria-label="Static pages mobile">
              <Link to="/#chi-siamo" onClick={closeMobileNav}>Chi siamo</Link>
              <Link to="/#servizi" onClick={closeMobileNav}>Servizi</Link>
              <Link to="/#contatti" onClick={closeMobileNav}>Contatti</Link>
            </nav>
          </div>

          <div className="dashboard-navbar__drawer-separator" aria-hidden="true"></div>

          <div className="dashboard-navbar__drawer-section">
            <nav className="dashboard-navbar__drawer-modules" aria-label="Moduli mobile">
              <Link to="/fatturazione" onClick={closeMobileNav}>Fatturazione</Link>
              <Link to="/scadenzario" onClick={closeMobileNav}>Scadenzario</Link>
              <Link to="/nota-spese" onClick={closeMobileNav}>Nota Spese</Link>
              <Link to="/burn-rate-meter" onClick={closeMobileNav}>Burn Rate Meter</Link>
              <Link to="/time-tracker" onClick={closeMobileNav}>Time-Tracker</Link>
              <Link to="/zero-carta" onClick={closeMobileNav}>Zero carta</Link>
              <Link to="/crm-clienti" onClick={closeMobileNav}>CRM clienti</Link>
            </nav>
          </div>
        </div>

        <div className="dashboard-navbar__drawer-separator" aria-hidden="true"></div>

        <div className="dashboard-navbar__drawer-actions">
          <button 
            type="button" 
            className="dashboard-navbar__drawer-profile"
            aria-label="Apri profilo utente"
          >
            <div className="dashboard-navbar__user-avatar" aria-hidden="true">
              <span className="material-symbols-outlined">person</span>
            </div>
          </button>
          <button 
            type="button" 
            className="dashboard-navbar__logout-btn" 
            onClick={handleLogout}
            aria-label="Logout"
          >
            <span className="material-symbols-outlined" aria-hidden="true">logout</span>
          </button>
        </div>
      </aside>
    </>
  )
}

export default SharedNavbar

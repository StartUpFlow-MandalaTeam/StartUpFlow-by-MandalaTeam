import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import DashboardLayout from './components/DashboardLayout'
import DashboardPage from './pages/DashboardPage'
import FatturazionePage from './pages/FatturazionePage'
import ScadenzarioPage from './pages/ScadenzarioPage'
import NotaSpesePage from './pages/NotaSpesePage'
import BurnRateMeterPage from './pages/BurnRateMeterPage'
import TimeTrackerPage from './pages/TimeTrackerPage'
import ZeroCartaPage from './pages/ZeroCartaPage'
import CrmClientiPage from './pages/CrmClientiPage'

function App() {
  return (
    <Router>
      <Routes>
        {/* Route principale: Dashboard */}
        <Route path="/" element={<DashboardLayout />}>
          <Route index element={<DashboardPage />} />
          
          {/* Route per i 7 servizi */}
          <Route path="fatturazione" element={<FatturazionePage />} />
          <Route path="scadenzario" element={<ScadenzarioPage />} />
          <Route path="nota-spese" element={<NotaSpesePage />} />
          <Route path="burn-rate-meter" element={<BurnRateMeterPage />} />
          <Route path="time-tracker" element={<TimeTrackerPage />} />
          <Route path="zero-carta" element={<ZeroCartaPage />} />
          <Route path="crm-clienti" element={<CrmClientiPage />} />
          
          {/* Redirect per route non valide */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </Router>
  )
}

export default App

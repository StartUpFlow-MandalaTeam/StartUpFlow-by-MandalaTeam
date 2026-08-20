import { Outlet } from 'react-router-dom'
import SharedNavbar from './SharedNavbar'

function DashboardLayout() {
  return (
    <div className="app-container">
      <SharedNavbar />
      <main className="main-content">
        <Outlet />
      </main>
    </div>
  )
}

export default DashboardLayout

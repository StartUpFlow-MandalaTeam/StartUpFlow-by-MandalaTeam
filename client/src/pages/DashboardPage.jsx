import { Link } from 'react-router-dom'

const cards = [
  { title: 'Fatturazione', description: 'Emetti le tue fatture', link: '/fatturazione' },
  { title: 'Scadenzario', description: 'Pagamenti & Incassi', link: '/scadenzario' },
  { title: 'Nota Spese', description: 'Registra le uscite', link: '/nota-spese' },
  { title: 'Burn Rate Meter', description: 'Il tuo stato di salute', link: '/burn-rate-meter' },
  { title: 'Time-Tracker', description: 'Tempo di fatturazione per cliente', link: '/time-tracker' },
  { title: '"Zero carta"', description: 'Repository documentale', link: '/zero-carta' },
  { title: 'CRM clienti', description: 'Ciclo di acquisizione clienti', link: '/crm-clienti' },
]

function DashboardPage() {
  return (
    <div className="card-container-wrapper">
      <div className="card-container">
        {/* Riga superiore: prime 4 card */}
        <div className="card-row card-row--top">
          {cards.slice(0, 4).map((card, index) => (
            <div className="card" key={index}>
              <img className="card-image" src="https://placehold.co/290x180" alt={card.title} />
              <div className="card-content">
                <h3 className="card-title">{card.title}</h3>
                <p className="card-description">{card.description}</p>
                <Link to={card.link} className="card-button">Vai!</Link>
              </div>
            </div>
          ))}
        </div>

        {/* Riga inferiore: ultime 3 card */}
        <div className="card-row card-row--bottom">
          {cards.slice(4).map((card, index) => (
            <div className="card" key={index + 4}>
              <img className="card-image" src="https://placehold.co/290x180" alt={card.title} />
              <div className="card-content">
                <h3 className="card-title">{card.title}</h3>
                <p className="card-description">{card.description}</p>
                <Link to={card.link} className="card-button">Vai!</Link>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

export default DashboardPage

export default function SearchBar({ value, onChange, placeholder='Search...' }) {
  return <input className="search-input" value={value} onChange={e=>onChange(e.target.value)} placeholder={placeholder}/>;
}

export function ShopCategoryFilter({ value, categories, onChange }: { value: string; categories: string[]; onChange: (category: string) => void }) {
  return (
    <div className="map-action-grid">
      <button className={value === 'all' ? 'btn primary' : 'btn'} onClick={() => onChange('all')}>All</button>
      {categories.map((category) => (
        <button key={category} className={value === category ? 'btn primary' : 'btn'} onClick={() => onChange(category)}>
          {category.replace(/_/g, ' ')}
        </button>
      ))}
    </div>
  );
}

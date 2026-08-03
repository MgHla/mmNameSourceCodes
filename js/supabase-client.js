import { SUPABASE_CONFIG } from './config.js';

class SupabaseClient {
    constructor() {
        this.url = SUPABASE_CONFIG.url;
        this.anonKey = SUPABASE_CONFIG.anonKey;
    }

    async query(table, options = {}) {
        const { select = '*', match, order, limit } = options;
        
        let queryUrl = `${this.url}/rest/v1/${table}?select=${encodeURIComponent(select)}`;
        
        if (match) {
            const params = new URLSearchParams();
            Object.entries(match).forEach(([key, value]) => {
                params.append(key, `eq.${value}`);
            });
            queryUrl += `&${params.toString()}`;
        }
        
        if (order) {
            queryUrl += `&order=${encodeURIComponent(order)}`;
        }
        
        if (limit) {
            queryUrl += `&limit=${limit}`;
        }

        try {
            const response = await fetch(queryUrl, {
                method: 'GET',
                headers: {
                    'apikey': this.anonKey,
                    'Authorization': `Bearer ${this.anonKey}`,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Query error:', error);
            throw error;
        }
    }
}

export default SupabaseClient;
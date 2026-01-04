import pandas as pd
import os

file_path = 'SMC Businesses Assessment Tool (1).xlsx'
try:
    xl = pd.ExcelFile(file_path)
    print(f"Sheet names: {xl.sheet_names}")
    for sheet in xl.sheet_names:
        df = xl.parse(sheet)
        print(f"\n--- Sheet: {sheet} ---")
        print(df.head())
        print(f"Shape: {df.shape}")
except Exception as e:
    print(f"Error reading excel file: {e}")
